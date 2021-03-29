<?php

namespace ethaniccc\Esoteric\check\combat\autoclicker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class AutoClickerB extends Check{

    public function __construct(){
        parent::__construct("Autoclicker", "B", "Checks for irregular clicking patterns", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if((($packet instanceof InventoryTransactionPacket && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) || ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE)) && $data->runClickChecks){
            if($data->skewness <= 0 && $data->kurtosis <= 0 && $data->outliers <= 1 && $data->cps >= 10){
                if(++$this->buffer >= 1.2){
                    $this->flag($data, [
                        "cps" => round($data->cps, 1)
                    ]);
                }
            } else {
                $this->reward(0.05);
                $this->buffer = max($this->buffer - 0.2, 0);
            }
        }
    }

}