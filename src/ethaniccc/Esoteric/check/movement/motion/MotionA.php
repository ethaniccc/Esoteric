<?php

namespace ethaniccc\Esoteric\check\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class MotionA extends Check{

    public function __construct(){
        parent::__construct("Motion", "A", "Checks for impossible upward motion", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket && !$data->onGround && $data->timeSinceFlight >= 10){
            $currentYMovement = $data->currentMoveDelta->y;
            if($data->timeSinceJump <= 1){
                $currentYMovement -= $data->jumpVelocity;
            }
            $lastYMovement = $data->lastMoveDelta->y;
            if($currentYMovement > $lastYMovement && $currentYMovement > 0.005 && !$data->isCollidedHorizontally
            && $data->timeSinceMotion > 1 && $data->ticksSinceInLiquid >= 10 && $data->ticksSinceInClimbable >= 10 && $data->ticksSinceInCobweb >= 10
            && $data->timeSinceTeleport > 1){
                $this->flag($data, [
                    "current" => round($currentYMovement, 3),
                    "last" => round($lastYMovement, 3)
                ]);
                $this->setback($data);
            } else {
                $this->reward(0.02);
            }
        }
    }

}