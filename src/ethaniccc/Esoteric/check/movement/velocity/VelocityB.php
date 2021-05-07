<?php

namespace ethaniccc\Esoteric\check\movement\velocity;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;

class VelocityB extends Check {

	public function __construct() {
		parent::__construct("Velocity", "B", "Checks if the player takes an abnormal amount of horizontal knockback", true);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			// TODO: Horizontal velocity check
		}
	}

}