<?php

namespace ethaniccc\Esoteric\check\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class MotionB extends Check{

    public function __construct(){
        parent::__construct("Motion", "B", "Checks if the player follows friction in-air", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if($packet instanceof PlayerAuthInputPacket && $data->offGroundTicks >= 3 && $data->timeSinceFlight >= 10){
            $currentXZ = MathUtils::hypot($data->currentMoveDelta->x, $data->currentMoveDelta->z);
            $lastXZ = MathUtils::hypot($data->lastMoveDelta->x, $data->lastMoveDelta->z);
            $prediction = $lastXZ * 0.91 + $data->jumpMovementFactor;
            if($data->timeSinceJump <= 1){
                $prediction += 0.3;
            }
            $diff = $currentXZ - $prediction;
            if($diff > 0.00001 && $data->timeSinceMotion > 1 && $data->ticksSinceInCobweb >= 10 && $data->ticksSinceInClimbable >= 10
            && $data->ticksSinceInLiquid >= 10 && $currentXZ > 0 && $lastXZ > 0 && $data->timeSinceTeleport > 1){
                if(++$this->buffer >= 2){
                    $this->flag($data, [
                        "diff" => round($diff, 5),
                        "sprint" => var_export($data->isSprinting, true)
                    ]);
                    $this->setback($data);
                }
            } else {
                $this->buffer = max($this->buffer - 0.02, 0);
            }
        }
    }

}