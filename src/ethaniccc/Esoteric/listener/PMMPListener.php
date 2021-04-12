<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class PMMPListener implements Listener {

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
		$playerData->inboundProcessor->execute($packet, $playerData);
		foreach ($playerData->checks as $check)
			if ($check->enabled())
				$check->inbound($packet, $playerData);
	}

	/**
	 * @param DataPacketSendEvent $event
	 * @priority LOWEST
	 * @ignoreCancelled true
	 */
	public function outbound(DataPacketSendEvent $event): void {
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		$playerData = Esoteric::getInstance()->dataManager->get($player) ?? Esoteric::getInstance()->dataManager->add($player);
		if ($packet instanceof BatchPacket) {
			$locationList = [];
			$key = null;
			$gen = PacketUtils::getAllInBatch($packet);
			while (($buff = $gen->current()) !== null) {
				$pk = PacketPool::getPacket($buff);
				try {
					try {
						$pk->decode();
					} catch (\RuntimeException $e) {
						$gen->next();
						continue;
					}
				} catch (\LogicException $e) {
					$gen->next();
					continue;
				}
				if (($pk instanceof MovePlayerPacket || $pk instanceof MoveActorDeltaPacket) && $pk->entityRuntimeId !== $playerData->player->getId()) {
					$locationList[] = clone $pk;
				} elseif ($pk instanceof NetworkStackLatencyPacket) {
					$key = $pk->timestamp;
				}

				$playerData->outboundProcessor->execute($pk, $playerData);
				foreach($playerData->checks as $check)
					if($check->handleOut())
						$check->outbound($pk, $playerData);
				$gen->next();
			}
			if ($playerData->loggedIn) {
				if ($playerData->entityLocationMap->key !== null) {
					if ($key !== $playerData->entityLocationMap->key && count($locationList) > 0) {
						$event->setCancelled();
						foreach ($locationList as $p) {
							$playerData->entityLocationMap->add($p);
						}
					}
				}
			}

		}
	}

}