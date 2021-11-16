<?php

namespace ethaniccc\Esoteric\check\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use pocketmine\block\BlockLegacyIds;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use function round;

class MotionA extends Check{

	private float $lastPreviousYMovement = 0.0;

	public function __construct(){
		parent::__construct("Motion", "A", "Checks for impossible upward motion", false);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data) : void{
		if($packet instanceof PlayerAuthInputPacket){
			if($data->ticksSinceFlight >= 10 && $data->inLoadedChunk && $data->ticksSinceGlide >= 5){
				$currentYMovement = $this->getRawYMotion($data);
				$lastYMovement = $data->lastMoveDelta->y;
				if($currentYMovement > MovementConstants::STEP_HEIGHT && $currentYMovement > $lastYMovement && $currentYMovement > $this->lastPreviousYMovement && $currentYMovement > 0.03 && $data->ticksSinceInLiquid >= 10 && $data->ticksSinceInClimbable >= 10 && $data->ticksSinceInCobweb >= 10 && !$data->teleported){
					$this->flag($data, ["current" => round($currentYMovement, 3), "last" => round($lastYMovement, 3), "preV" => round($this->lastPreviousYMovement, 3)]);
					$this->setback($data);
				}else{
					$this->reward(0.02);
				}
			}

			$this->lastPreviousYMovement = $data->lastMoveDelta->y;
		}
	}

	private function getRawYMotion(PlayerData $data) : float{
		$currentYMovement = $data->currentMoveDelta->y;
		if($data->ticksSinceJump <= 1){
			$currentYMovement -= $data->jumpVelocity;
		}
		// possible 2 tick offset wtf
		if($data->ticksSinceMotion <= 2){
			$currentYMovement -= $data->motion->y;
		}

		foreach($data->lastBlocksBelow as $block){
			if($block->getId() === BlockLegacyIds::SLIME_BLOCK){
				$this->lastPreviousYMovement *= -1;
				break;
			}elseif($block->getId() === BlockLegacyIds::BED_BLOCK){
				$currentYMovement -= 0.658;
				break;
			}
		}

		if($data->ticksSinceInClimbable <= 5){
			$currentYMovement -= 0.2;
		}

		if($data->isCollidedHorizontally){
			$currentYMovement -= MovementConstants::STEP_HEIGHT * 1.5;
		}
		return $currentYMovement;
	}

}