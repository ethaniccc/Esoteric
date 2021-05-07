<?php

namespace ethaniccc\Esoteric\check\misc\packets;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;

class PacketsB extends Check {

	private $delay = 0;

	public function __construct() {
		parent::__construct("Packets", "B", "Checks if the player is sending the wrong movement packet", false);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			++$this->delay;
		} elseif ($packet instanceof MovePlayerPacket) {
			if ($this->delay < 2 && !$data->immobile) {
				$this->flag($data, ["delay" => $this->delay]);
			}
			$this->delay = 0;
		}
	}

}