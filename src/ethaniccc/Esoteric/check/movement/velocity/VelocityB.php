<?php

namespace ethaniccc\Esoteric\check\movement\velocity;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\DataPacket;

class VelocityB extends Check {

	public function __construct() {
		parent::__construct("Velocity", "B", "Checks if the player takes an abnormal amount of horizontal knockback", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
		}
	}

}