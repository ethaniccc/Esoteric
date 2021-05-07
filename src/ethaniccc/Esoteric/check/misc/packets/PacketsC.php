<?php

namespace ethaniccc\Esoteric\check\misc\packets;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\protocol\InputConstants;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;

class PacketsC extends Check {

	private $delayTicks = 0;

	public function __construct() {
		parent::__construct("Packets", "C", "Checks if the user is jumping while not pressing the jump key", false);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket && $data->loggedIn && $data->ticksSinceSpawn >= 20) {
			$hasJumpFlag = InputConstants::hasFlag($packet, InputConstants::JUMPING);
			if ($data->ticksSinceJump <= 1 && !$hasJumpFlag) {
				$this->flag($data);
				$this->setback($data);
			} elseif ($this->delayTicks > 0 && $data->ticksSinceJump <= 1 && $hasJumpFlag) {
				$this->flag($data, ["delay" => 10 - $this->delayTicks]);
				$this->setback($data);
			} else {
				$this->reward(0.001);
			}

			if ($hasJumpFlag && $data->ticksSinceJump <= 1) {
				$this->delayTicks = 10;
			} elseif (!$hasJumpFlag) {
				$this->delayTicks = 0;
			}

			--$this->delayTicks;
		}
	}

}