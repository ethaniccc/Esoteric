<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\GeneralUtils;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\TextFormat;

class PMMPListener implements Listener {

	/** @var TimingsHandler */
	public $checkTimings;
	public $sendTimings;

	public function __construct() {
		$this->checkTimings = new TimingsHandler("Esoteric Checks");
		$this->sendTimings = new TimingsHandler("Esoteric Listener Outbound");
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
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @priority LOWEST
	 */
	public function outbound(DataPacketSendEvent $event): void {
		$this->sendTimings->startTiming();
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		$playerData = Esoteric::getInstance()->dataManager->get($player);
		if ($playerData === null) {
			$this->sendTimings->stopTiming();
			return;
		}
		if ($playerData->isDataClosed) {
			$this->sendTimings->stopTiming();
			return;
		}
		if ($packet instanceof BatchPacket) {
			$gen = PacketUtils::getFirst($packet);
			if ($gen->getX() !== "" && $gen->getY()) {
				$targetPacket = PacketPool::getPacket($gen->getX());
				try {
					try {
						$targetPacket->decode();
					} catch (\RuntimeException $e) {
						$this->sendTimings->stopTiming();
						return;
					}
				} catch (\LogicException $e) {
					$this->sendTimings->stopTiming();
					return;
				}
			} else {
				$this->sendTimings->stopTiming();
				return;
			}

			if ($playerData->loggedIn && ($targetPacket instanceof MovePlayerPacket && $targetPacket->entityRuntimeId !== $player->getId() || $targetPacket instanceof MoveActorDeltaPacket)) {
				$playerData->entityLocationMap->add($targetPacket);
				$event->setCancelled();
			}

			if (!$event->isCancelled()) {
				$playerData->outboundProcessor->execute($targetPacket, $playerData);
				// foreach ($playerData->checks as $check) if ($check->handleOut()) $check->outbound($targetPacket, $playerData);
			}
			$this->sendTimings->stopTiming();
		}
	}

	public function onLevelChange(EntityLevelChangeEvent $event): void {
		$entity = $event->getEntity();
		if ($entity instanceof Player) {
			$data = Esoteric::getInstance()->dataManager->get($entity);
			if ($data === null) {
				return;
			}
			$data->inLoadedChunk = false;
		}
	}

}