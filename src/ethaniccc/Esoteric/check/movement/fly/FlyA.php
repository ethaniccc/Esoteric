<?php

namespace ethaniccc\Esoteric\check\movement\fly;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use function abs;
use function max;
use function min;
use function round;

class FlyA extends Check {

	private $lastBlockAbove = false;

	public function __construct() {
		parent::__construct("Fly", "A", "Estimates the next Y movement of the player", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket && $data->offGroundTicks >= 7 && $data->ticksSinceFlight >= 10 && $data->ticksSinceGlide >= 3) {
			$predictedYMovement = (($this->lastBlockAbove ? 0 : $data->lastMoveDelta->y) - MovementConstants::NORMAL_GRAVITY) * MovementConstants::GRAVITY_MULTIPLICATION;
			$difference = abs($data->currentMoveDelta->y - $predictedYMovement);
			if ($difference > $this->option("diff_max", 0.015) && !$data->teleported && $data->ticksSinceMotion > 1 && $data->ticksSinceInLiquid >= 5 && $data->ticksSinceInClimbable >= 5 && $data->ticksSinceInCobweb >= 5 && abs($predictedYMovement) > 0.005 && !$data->immobile && $data->inLoadedChunk && !$data->isCollidedHorizontally && !$data->isInVoid) {
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