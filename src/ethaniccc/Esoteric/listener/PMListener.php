<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\Esoteric;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;

final class PMListener implements Listener {

	public function receive(DataPacketReceiveEvent $event): void {
		$player = $event->getPlayer();
		$packet = $event->getPacket();
		if ($packet instanceof BatchPacket) return;
		$data = Esoteric::getInstance()->dataStorage->get($player, true);
		$data->inboundHandler->execute($packet);
	}

	public function send(DataPacketSendEvent $event): void {
		$player = $event->getPlayer();
		$packet = $event->getPacket();
		$data = Esoteric::getInstance()->dataStorage->get($player);
		if ($data === null) {
			return;
		}
		if ($packet instanceof StartGamePacket) {
			$packet->playerMovementSettings = new PlayerMovementSettings(PlayerMovementType::SERVER_AUTHORITATIVE_V2_REWIND, 0, false);
		}
	}

}