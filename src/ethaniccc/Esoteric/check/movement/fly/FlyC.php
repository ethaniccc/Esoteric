<?php

namespace ethaniccc\Esoteric\check\movement\fly;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;

class FlyC extends Check {

	public function __construct() {
		parent::__construct("Fly", "C", "Checks if the player is jumping on the air", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerActionPacket && $packet->action === PlayerActionPacket::ACTION_JUMP && !$data->onGround && !$data->immobile) {
			$this->flag($data);
			$this->setback($data);
		}
	}

}