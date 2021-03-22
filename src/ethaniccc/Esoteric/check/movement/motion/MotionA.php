<?php

namespace ethaniccc\Esoteric\data\sub\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class MotionA extends Check{

    public function __construct(){
        parent::__construct("Motion", "A", "Checks for impossible upward motion", true);
    }

    public function inbound(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket && $data->offGroundTicks >= 10){
            if($data->currentMoveDelta->y > 0 && $data->lastMoveDelta->y < 0 && !$data->isCollidedHorizontally && !$data->isCollidedVertically){

            } else {
                $this->buffer = 0.0;
            }
        }
    }

}