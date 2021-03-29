<?php

namespace ethaniccc\Esoteric\check\packet\badpacket;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class BadPacketsA extends Check{

    private $delay = 0;

    public function __construct(){
        parent::__construct("BadPackets", "A", "Checks if the wrong movement packet was sent", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if($packet instanceof PlayerAuthInputPacket){
            ++$this->delay;
        } elseif($packet instanceof MovePlayerPacket){
            if($this->delay < 2){
                $this->flag($data, [
                    "delay" => $this->delay
                ]);
            }
            $this->delay = 0;
        }
    }

}