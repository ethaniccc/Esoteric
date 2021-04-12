<?php

namespace ethaniccc\Esoteric\check\movement\jesus;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\block\Water;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class JesusA extends Check {

	public function __construct() {
		parent::__construct("Jesus", "A", "Checks if the player is walking on water", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket && $data->onGround) {
			$player = $data->player;
			$block = $player->getLevel()->getBlockAt($player->x, $player->y - 0.5, $player->z);
			if ($block instanceof Water) {
				$this->flag($data);
				$this->setback($data);
			}
		}
	}

}