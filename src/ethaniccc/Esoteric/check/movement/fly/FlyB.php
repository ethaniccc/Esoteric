<?php

namespace ethaniccc\Esoteric\check\movement\fly;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class FlyB extends Check{

    public function __construct(){
        parent::__construct("Fly", "B", "Checks for consistent Y movement", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket && $data->offGroundTicks >= 5){
            $diff = abs($data->currentMoveDelta->y - $data->lastMoveDelta->y);
            if($diff < 1E-4 && $data->ticksSinceInCobweb >= 10 && $data->ticksSinceInClimbable >= 10 && $data->ticksSinceInLiquid >= 10 && $data->timeSinceMotion >= 3
            && $data->currentMoveDelta->y > -3.0){
                if(++$this->buffer >= 3){
                    $this->flag($data, ["diff" => round($diff, 3)]);
                    if($this->option("setback", false)) $this->setback($data, Esoteric::getInstance()->getSettings()->getSetbackType());
                }
            } else {
                $this->reward();
                $this->buffer = max($this->buffer - 0.02, 0);
            }
        }
    }

}