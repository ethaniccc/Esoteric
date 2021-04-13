<?php

namespace ethaniccc\Esoteric\check\movement\fly;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class FlyA extends Check {

	private $lastBlockAbove = false;

	public function __construct() {
		parent::__construct("Fly", "A", "Estimates the next Y movement of the player", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket && $data->offGroundTicks >= 3 && $data->ticksSinceFlight >= 10) {
			$predictedYMovement = (($this->lastBlockAbove ? 0 : $data->lastMoveDelta->y) - MovementConstants::Y_SUBTRACTION) * MovementConstants::Y_MULTIPLICATION;
			$difference = abs($data->currentMoveDelta->y - $predictedYMovement);
			if ($difference > $this->option("diff_max", 0.015) && !$data->teleported && $data->ticksSinceMotion > 1 && $data->ticksSinceInLiquid >= 5 && $data->ticksSinceInClimbable >= 5 && $data->ticksSinceInCobweb >= 5 && abs($predictedYMovement) > 0.005 && !$data->isCollidedHorizontally) {
				if (++$this->buffer >= 2) {
					$this->flag($data, ["diff" => round($difference, 4)]);
					$this->setback($data);
					$this->buffer = min($this->buffer, 4);
				}
			} else {
				$this->reward();
				$this->buffer = max($this->buffer - 0.25, 0);
			}
			$this->lastBlockAbove = $data->hasBlockAbove;
		}
	}
}