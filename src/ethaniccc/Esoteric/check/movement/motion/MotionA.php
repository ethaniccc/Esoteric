<?php

namespace ethaniccc\Esoteric\check\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use pocketmine\block\BlockIds;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class MotionA extends Check {

	private $lastPreviousYMovement = 0.0;

	public function __construct() {
		parent::__construct("Motion", "A", "Checks for impossible upward motion", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			if ($data->ticksSinceFlight >= 10) {
				$currentYMovement = $data->currentMoveDelta->y;
				if ($data->ticksSinceJump === 1) {
					$currentYMovement -= $data->jumpVelocity;
				}
				// possible 1  tick offset wtf
				if ($data->ticksSinceMotion <= 2) {
					$currentYMovement -= $data->motion->y;
				}

				foreach ($data->lastBlocksBelow as $block) {
					if ($block->getId() === BlockIds::SLIME_BLOCK) {
						$this->lastPreviousYMovement *= -1;
						break;
					} elseif ($block->getId() === BlockIds::BED_BLOCK) {
						$currentYMovement -= 0.658;
						break;
					}
				}

				if ($data->ticksSinceInClimbable) {
					$currentYMovement -= 0.2;
				}

				if ($data->isCollidedHorizontally) {
					$currentYMovement -= MovementConstants::STEP_HEIGHT * 1.5;
				}

				$lastYMovement = $data->lastMoveDelta->y;
				if ($currentYMovement > $lastYMovement && $currentYMovement > $this->lastPreviousYMovement && $currentYMovement > 0.005 && $data->ticksSinceInLiquid >= 10 && $data->ticksSinceInClimbable >= 10 && $data->ticksSinceInCobweb >= 10 && !$data->teleported) {
					$this->flag($data, ["current" => round($currentYMovement, 3), "last" => round($lastYMovement, 3), "preV" => round($this->lastPreviousYMovement, 3)]);
					$this->setback($data);
				} else {
					$this->reward(0.02);
				}
			}

			$this->lastPreviousYMovement = $data->lastMoveDelta->y;
		}
	}

}