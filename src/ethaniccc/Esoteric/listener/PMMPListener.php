<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\PacketUtils;
use LogicException;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\Binary;
use pocketmine\utils\TextFormat;
use RuntimeException;
use function count;
use function in_array;
use function round;
use function str_replace;
use function strlen;
use function strtolower;
use function var_export;

class PMMPListener implements Listener {

	/** @var TimingsHandler */
	public $checkTimings;
	public $sendTimings;
	public $decodingTimings;

	private const USED_OUTBOUND_PACKETS = [
		ProtocolInfo::MOVE_PLAYER_PACKET, ProtocolInfo::MOVE_ACTOR_DELTA_PACKET, ProtocolInfo::UPDATE_BLOCK_PACKET,
		ProtocolInfo::SET_ACTOR_MOTION_PACKET, ProtocolInfo::MOB_EFFECT_PACKET, ProtocolInfo::SET_PLAYER_GAME_TYPE_PACKET,
		ProtocolInfo::SET_ACTOR_DATA_PACKET, ProtocolInfo::NETWORK_CHUNK_PUBLISHER_UPDATE_PACKET, ProtocolInfo::ADVENTURE_SETTINGS_PACKET,
		ProtocolInfo::ACTOR_EVENT_PACKET, ProtocolInfo::UPDATE_ATTRIBUTES_PACKET, ProtocolInfo::CORRECT_PLAYER_MOVE_PREDICTION_PACKET
	];

	public function __construct() {
		$this->checkTimings = new TimingsHandler("Esoteric Checks");
		$this->sendTimings = new TimingsHandler("Esoteric Listener Outbound");
		$this->decodingTimings = new TimingsHandler("Esoteric Batch Decoding");
	}

	/**
	 * @param PlayerPreLoginEvent $event
	 * @priority LOWEST
	 */
	public function log(PlayerPreLoginEvent $event): void {
		foreach (Server::getInstance()->getNameBans()->getEntries() as $entry) {
			if ($entry->getSource() === "Esoteric AC") {
				$event->setCancelled();
				$event->setKickMessage($entry->getReason());
				break;
			}
		}
	}

	/**
	 * @param PlayerQuitEvent $event
	 * @priority LOWEST
	 */
	public function quit(PlayerQuitEvent $event): void {
		$data = Esoteric::getInstance()->dataManager->get($event->getPlayer());
		$message = null;
		foreach ($data->checks as $check) {
			$checkData = $check->getData();
			if ($checkData["violations"] >= 1) {
				if ($message === null) {
					$message = "";
				}
				$message .= TextFormat::YELLOW . $checkData["full_name"] . TextFormat::WHITE . " - " . $checkData["description"] . TextFormat::GRAY . " (" . TextFormat::RED . "x" . var_export(round($checkData["violations"], 3), true) . TextFormat::GRAY . ")" . PHP_EOL;
			}
		}
		Esoteric::getInstance()->logCache[strtolower($event->getPlayer()->getName())] = $message === null ? TextFormat::GREEN . "This player has no logs" : $message;
		Esoteric::getInstance()->dataManager->remove($event->getPlayer());
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 * @priority HIGHEST
	 * @ignoreCancelled false
	 */
	public function inbound(DataPacketReceiveEvent $event): void {
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		$playerData = Esoteric::getInstance()->dataManager->get($player) ?? Esoteric::getInstance()->dataManager->add($player);
		if ($playerData->isDataClosed) {
			return;
		}
		$playerData->inboundProcessor->execute($packet, $playerData);
		$this->checkTimings->startTiming();
		foreach ($playerData->checks as $check) {
			if ($check->enabled()) {
				$check->getTimings()->startTiming();
				$check->inbound($packet, $playerData);
				$check->getTimings()->stopTiming();
			}
		}
		$this->checkTimings->stopTiming();
		if ($packet instanceof PlayerAuthInputPacket) {
			$event->setCancelled();
		}
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @priority LOWEST
	 */
	public function outbound(DataPacketSendEvent $event): void {
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		$playerData = Esoteric::getInstance()->dataManager->get($player);
		if ($playerData === null) {
			return;
		}
		if ($playerData->isDataClosed) {
			return;
		}
		if ($packet instanceof BatchPacket) {
			$this->sendTimings->startTiming();
			$gen = PacketUtils::getAllInBatch($packet);
			foreach ($gen as $buff) {
				$pk = PacketPool::getPacket($buff);
				if (!in_array($pk->pid(), self::USED_OUTBOUND_PACKETS)) continue;
				$this->decodingTimings->startTiming();
				try {
					$pk->decode();
				} catch (RuntimeException|LogicException $e) {
					continue;
				}
				$this->decodingTimings->stopTiming();
				if (($pk instanceof MovePlayerPacket || $pk instanceof MoveActorDeltaPacket) && $pk->entityRuntimeId !== $playerData->player->getId()) {
					if ($playerData->entityLocationMap->get($pk->entityRuntimeId) !== null) {
						if (count($gen) === 1) {
							$event->setCancelled();
						} else {
							$packet->buffer = str_replace(Binary::writeUnsignedVarInt(strlen($pk->buffer)) . $pk->buffer, "", $packet->buffer);
							$packet->payload = str_replace(Binary::writeUnsignedVarInt(strlen($pk->buffer)) . $pk->buffer, "", $packet->payload);
						}
					}
					$playerData->entityLocationMap->add($pk);
				} elseif ($pk instanceof MovePlayerPacket && $pk->mode === MovePlayerPacket::MODE_TELEPORT && $pk->entityRuntimeId === $playerData->player->getId()) {

					/**
					 * Apparently, teleports is what was causing the crashes on Velvet. I suspect that it's probably something in Prim's server core that
					 * is causing this, but no evidence has been found supporting my theory. This fixes the crashes, but very honestly, this is very very dumb
					 */

					$pk->mode = MovePlayerPacket::MODE_RESET;
					$pk->encode();
					$p = new BatchPacket();
					$p->addPacket($pk);
					$p->encode();
					PacketUtils::sendPacketSilent($playerData, $p);
					if (count($gen) === 1) {
						$event->setCancelled();
					} else {
						$packet->buffer = str_replace(Binary::writeUnsignedVarInt(strlen($pk->buffer)) . $pk->buffer, "", $packet->buffer);
						$packet->payload = str_replace(Binary::writeUnsignedVarInt(strlen($pk->buffer)) . $pk->buffer, "", $packet->payload);
					}
				}
				$playerData->outboundProcessor->execute($pk, $playerData);
				foreach ($playerData->checks as $check)
					if ($check->handleOut())
						$check->outbound($pk, $playerData);
			}
			$this->sendTimings->stopTiming();
		} elseif ($packet instanceof StartGamePacket) {
			$packet->playerMovementSettings = new PlayerMovementSettings(PlayerMovementType::SERVER_AUTHORITATIVE_V2_REWIND, 20, false);
		}
	}

	public function onLevelChange(EntityLevelChangeEvent $event): void {
		$entity = $event->getEntity();
		if ($entity instanceof Player) {
			$data = Esoteric::getInstance()->dataManager->get($entity);
			if ($data !== null) {
				$data->inLoadedChunk = false;
			}
		}
	}

}