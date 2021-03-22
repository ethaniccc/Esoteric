<?php

namespace ethaniccc\Esoteric\check\movement\velocity;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
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
            }
            if($this->motionY > 0.0){
                $percentage = ($data->currentMoveDelta->y / $this->motionY) * 100;
                if($percentage < 99.99 && !$data->hasBlockAbove){
                    if(++$this->buffer >= 4) $this->flag($data, ["pct" => round($percentage, 4) . "%"]);
                } elseif($data->isCollidedVertically && !$data->hasBlockAbove){
                    // you shouldn't ever be collided vertically after taking motion... lol (unless there is a block above you)
                    if(++$this->buffer >= 4) $this->flag($data, ["pct" => "0%"]);
                } else {
                    $this->buffer = max($this->buffer - 0.5, 0);
                }
                $data->player->sendMessage("pct=$percentage%");
                $this->motionY = ($this->motionY - 0.08) * 0.980000012;
            }
        }
    }

}