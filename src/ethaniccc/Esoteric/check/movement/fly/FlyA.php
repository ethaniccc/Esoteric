<?php

namespace ethaniccc\Esoteric\check\movement\fly;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class FlyA extends Check{

    public function __construct(){
        parent::__construct("Fly", "A", "Predicts the next Y movement of the player", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket && $data->offGroundTicks >= 5){
            $currentYMovement = $data->currentMoveDelta->y;
            $expectedYMovement = ($data->lastMoveDelta->y - MovementConstants::Y_SUBTRACTION) * MovementConstants::Y_MULTIPLICATION;
            $diff = abs($currentYMovement - $expectedYMovement);
            if($diff > $this->option("diff_max", 0.01) && $data->timeSinceMotion >= 3 && !$data->hasBlockAbove && $data->ticksSinceInLiquid >= 10 && $data->ticksSinceInClimbable >= 10 && $data->ticksSinceInCobweb >= 10){
                if(++$this->buffer >= 3){
                    $this->flag($data, ["diff" => round($diff, 3)]);
                    if($this->option("setback", false)) $this->setback($data, Esoteric::getInstance()->getSettings()->getSetbackType());
                }
            } else {
                $this->reward();
                $this->buffer = max($this->buffer - 0.05, 0);
            }
        }
    }

}