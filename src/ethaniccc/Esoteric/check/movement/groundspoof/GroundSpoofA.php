<?php

namespace ethaniccc\Esoteric\check\movement\groundspoof;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class GroundSpoofA extends Check {

	public function __construct() {
		parent::__construct("GroundSpoof", "A", "Checks if the player is spoofing their onGround value", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket && $data->onGround && !$data->expectedOnGround && $data->ticksSinceFlight >= 10 && !$data->teleported
		&& !$data->isInVoid && !$data->immobile && $data->inLoadedChunk && $data->player->isAlive()) {
			$this->flag($data);
		} else {
			$this->reward();
		}
	}

}