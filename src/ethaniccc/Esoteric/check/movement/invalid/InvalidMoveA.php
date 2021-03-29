<?php

namespace ethaniccc\Esoteric\check\movement\invalid;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\InputConstants;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class InvalidMoveA extends Check{

    private $delayTicks = 0;

    public function __construct(){
        parent::__construct("InvalidMove", "A", "Checks for an invalid jump and checks for an invalid jump delay", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if($packet instanceof PlayerAuthInputPacket && $data->timeSinceJoin >= 20){
            $hasJumpFlag = InputConstants::hasFlag($packet, InputConstants::JUMPING);
            if($data->timeSinceJump <= 1 && !$hasJumpFlag){
                $this->flag($data);
                $this->setback($data);
            } elseif($this->delayTicks > 0 && $data->timeSinceJump <= 1 && $hasJumpFlag){
                $this->flag($data, [
                    "delay" => 10 - $this->delayTicks
                ]);
                $this->setback($data);
            } else {
                $this->reward(0.001);
            }

            if($hasJumpFlag && $data->timeSinceJump <= 1){
                $this->delayTicks = 10;
            } elseif(!$hasJumpFlag){
                $this->delayTicks = 0;
            }

            --$this->delayTicks;
        }
    }

}