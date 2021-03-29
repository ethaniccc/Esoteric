<?php

namespace ethaniccc\Esoteric\check\combat\aimassist;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\EvictingList;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class AimAssistA extends Check{

    public function __construct(){
        parent::__construct("AimAssist", "A", "Checks for rounded head movement", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket && $data->currentYawDelta > 0.0065){
            $roundedDiff = abs(round($data->currentYawDelta, 1) - round($data->currentYawDelta, 5));
            if($roundedDiff <= 3E-5){
                if(++$this->buffer >= 4) $this->flag($data, ["diff" => round($roundedDiff, 3)]);
            } else {
                $this->reward(0.005);
                $this->buffer = max($this->buffer - 0.05, 0);
            }
        }
    }

}