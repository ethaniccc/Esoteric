<?php

namespace ethaniccc\Esoteric\check\movement\fly;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\DataPacket;

class FlyC extends Check {

	public function __construct() {
		parent::__construct("Fly", "C", "Checks if the player is jumping on the air", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket && $data->ticksSinceJump === 1 && $data->offGroundTicks > 2 && !$data->immobile && !$data->isInVoid) {
			$this->flag($data);
			$this->setback($data);
		}
	}

}