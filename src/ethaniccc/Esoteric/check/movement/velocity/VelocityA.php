<?php

namespace ethaniccc\Esoteric\check\movement\velocity;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class VelocityA extends Check{

    private $motionY = 0.0;

    public function __construct(){
        parent::__construct("Velocity", "A", "Checks for lower vertical knockback", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if($packet instanceof PlayerAuthInputPacket){
            if($data->timeSinceMotion <= 1){
                $this->motionY = $data->motion->y;
                if($data->timeSinceJump <= 1){
                    $this->motionY = $data->jumpVelocity;
                }
            }
            if($this->motionY > 0.0){
                $percentage = ($data->currentMoveDelta->y / $this->motionY) * 100;
                if(($percentage < $this->option("min_pct", 99.99) || $percentage > $this->option("max_pct", 105.0)) && !$data->hasBlockAbove && $data->ticksSinceInCobweb >= 10 && $data->ticksSinceInLiquid >= 10 && $data->ticksSinceInClimbable >= 10){
                    if(++$this->buffer >= 4) $this->flag($data, ["pct" => round($percentage, 4) . "%"]);
                } elseif($percentage <= 0.0 && $data->ticksSinceInCobweb >= 10 && $data->ticksSinceInClimbable >= 10 && $data->ticksSinceInLiquid >= 10){
                    if(++$this->buffer >= 4) $this->flag($data, ["pct" => "0%"]);
                } else {
                    $this->buffer = max($this->buffer - 0.5, 0);
                }
                $data->player->sendMessage("pct=$percentage%");
                if(!$data->hasBlockAbove){
                    $this->motionY = ($this->motionY - 0.08) * 0.980000012;
                } else {
                    $this->motionY = 0.0;
                    $this->buffer = 0;
                }
            }
        }
    }

}