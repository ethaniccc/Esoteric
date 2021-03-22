<?php

namespace ethaniccc\Esoteric\check\combat\aimassist;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\EvictingList;
use ethaniccc\Esoteric\utils\Pair;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class AimAssistA extends Check{

    private $graph;

    public function __construct(){
        parent::__construct("AimAssist", "A", "Checks for the coefficient of determination in a graph", true);
        $this->graph = new EvictingList(20);
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