<?php

namespace ethaniccc\Esoteric\check\misc\packets;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use function abs;
use function floor;

class PacketsA extends Check {

	public function __construct() {
		parent::__construct("Packets", "A", "Checks if the player's pitch goes beyond a certain threshold", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket && abs($packet->getPitch()) > 92) {
			$this->flag($data, ["pitch" => floor($packet->getPitch())]);
		}
	}

}