<?php

namespace ethaniccc\Esoteric\check\misc\packets;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class PacketsB extends Check {

	private $delay = 0;

	public function __construct() {
		parent::__construct("Packets", "B", "Checks if the player is sending the wrong movement packet", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			++$this->delay;
		} elseif ($packet instanceof MovePlayerPacket) {
			if ($this->delay < 2) {
				$this->flag($data);
			}
			$this->delay = 0;
		}
	}

}