<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\process\NetworkStackLatencyHandler;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\PacketUtils;
use LogicException;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\Binary;
use pocketmine\utils\TextFormat;
use RuntimeException;
use function array_keys;
use function count;
use function in_array;
use function round;
use function spl_object_id;
use function str_replace;
use function strlen;
use function strtolower;
use function var_export;
use const PHP_EOL;

class PMMPListener implements Listener {

	private const USED_OUTBOUND_PACKETS = [ProtocolInfo::MOVE_PLAYER_PACKET, ProtocolInfo::MOVE_ACTOR_ABSOLUTE_PACKET, ProtocolInfo::UPDATE_BLOCK_PACKET, ProtocolInfo::SET_ACTOR_MOTION_PACKET, ProtocolInfo::MOB_EFFECT_PACKET, ProtocolInfo::SET_PLAYER_GAME_TYPE_PACKET, ProtocolInfo::SET_ACTOR_DATA_PACKET, ProtocolInfo::NETWORK_CHUNK_PUBLISHER_UPDATE_PACKET, ProtocolInfo::ADVENTURE_SETTINGS_PACKET, ProtocolInfo::ACTOR_EVENT_PACKET, ProtocolInfo::UPDATE_ATTRIBUTES_PACKET, ProtocolInfo::CORRECT_PLAYER_MOVE_PREDICTION_PACKET, ProtocolInfo::NETWORK_STACK_LATENCY_PACKET, ProtocolInfo::REMOVE_ACTOR_PACKET, ProtocolInfo::ADD_ACTOR_PACKET, ProtocolInfo::ADD_PLAYER_PACKET,];

	/** @var TimingsHandler */
	public $checkTimings;
	public $sendTimings;
	public $decodingTimings;

	/** @var PlayerData[][] */
	public $levelChunkReceivers = [];

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
			if ($entry->getSource() === 'Esoteric AC' && $entry->getName() === strtolower($event->getPlayer()->getName())) {
				$event->setCancelled();
				$event->setKickMessage(str_replace(['{prefix}', '{code}', '{expires}'], [Esoteric::getInstance()->getSettings()->getPrefix(), $entry->getReason(), $entry->getExpires() !== null ? $entry->getExpires()->format("m/d/y h:i A T") : 'Never'], Esoteric::getInstance()->getSettings()->getBanMessage()));
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
		if ($data === null) {
			return;
		}
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
		foreach ($this->levelChunkReceivers as $id => $queue) {
			foreach ($queue as $key => $other) {
				if ($other->hash === $data->hash) {
					unset($this->levelChunkReceivers[$id][$key]);
				}
			}
		}
		Esoteric::getInstance()->logCache[strtolower($event->getPlayer()->getName())] = $message === null ? TextFormat::GREEN . "This player has no logs" : $message;
		Esoteric::getInstance()->dataManager->remove($event->getPlayer());
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority LOWEST
	 */
	public function join(PlayerJoinEvent $event): void {
		$data = Esoteric::getInstance()->dataManager->get($event->getPlayer());
		if ($data !== null) {
			Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleTask(new ClosureTask(function () use ($data): void {
				$data->hasAlerts = $data->player->hasPermission("ac.alerts");
			}));
		}
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
		$playerData->inboundProcessor->execute($packet, $playerData);
		if (($player->loggedIn && in_array($player->getName(), Esoteric::getInstance()->exemptList)) || $playerData->isDataClosed || $playerData->playerOS === DeviceOS::PLAYSTATION) {
			return;
		}
		if ($packet instanceof PlayerAuthInputPacket) {
			$event->setCancelled();
		}
		foreach ($playerData->checks as $check) {
			if ($check->enabled()) {
				$check->getTimings()->startTiming();
				$check->inbound($packet, $playerData);
				$check->getTimings()->stopTiming();
			}
		}
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @priority LOWEST
	 */
	public function outbound(DataPacketSendEvent $event): void {
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		if (in_array($player->getName(), Esoteric::getInstance()->exemptList)) {
			return;
		}
		$playerData = Esoteric::getInstance()->dataManager->get($player);
		if ($playerData === null || $playerData->isDataClosed || $playerData->playerOS === DeviceOS::PLAYSTATION) {
			return;
		}
		if ($packet instanceof BatchPacket) {
			$this->sendTimings->startTiming();
			if ($packet->getCompressionLevel() > 0) {
				$id = spl_object_id($packet);
				if (!isset($this->levelChunkReceivers[$id])) {
					Esoteric::getInstance()->chunkThread->queue($packet, function ($chunks) use ($id) {
						foreach ($chunks as $chunk) {
							foreach ($this->levelChunkReceivers[$id] as $data) {
								if ($data->loggedIn) {
									NetworkStackLatencyHandler::getInstance()->send($data, NetworkStackLatencyHandler::getInstance()->next($data), function (int $timestamp) use ($data, $chunk): void {
										$data->world->addChunk(clone $chunk);
									});
								} else {
									$data->world->addChunk(clone $chunk);
								}
							}
						}
						unset($this->levelChunkReceivers[$id]);
					});
				}
				$this->levelChunkReceivers[$id][] = $playerData;
			}
			$gen = PacketUtils::getAllInBatch($packet);
			foreach ($gen as $buff) {
				$pk = PacketPool::getPacket($buff);
				if (!in_array($pk->pid(), self::USED_OUTBOUND_PACKETS)) {
					continue;
				}
				try {
					$this->decodingTimings->startTiming();
					$pk->decode();
					$this->decodingTimings->stopTiming();
				} catch (RuntimeException | LogicException $e) {
					continue;
				}
				if (($pk instanceof MovePlayerPacket || $pk instanceof MoveActorAbsolutePacket) && $pk->entityRuntimeId !== $playerData->player->getId()) {
					if ($playerData->entityLocationMap->get($pk->entityRuntimeId) !== null) {
						if (count($gen) === 1) {
							$event->setCancelled();
						} else {
							$packet->buffer = str_replace(zlib_encode(Binary::writeUnsignedVarInt(strlen($pk->buffer)) . $pk->buffer, ZLIB_ENCODING_RAW, $packet->getCompressionLevel()), "", $packet->buffer);
							$packet->payload = str_replace(Binary::writeUnsignedVarInt(strlen($pk->buffer)) . $pk->buffer, "", $packet->payload);
						}
						$playerData->entityLocationMap->addPacket($pk);
					}
				}
				$playerData->outboundProcessor->execute($pk, $playerData);
				foreach ($playerData->checks as $check) {
					if ($check->handleOut()) {
						$check->outbound($pk, $playerData);
					}
				}
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
				$chunkKeys = array_keys($data->world->getAllChunks());
				foreach ($chunkKeys as $key) {
					$data->world->removeChunkByHash($key);
				}
			}
		}
	}

}
