<?php

namespace ethaniccc\Esoteric\check\combat\autoclicker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class AutoClickerA extends Check{

    public function __construct(){
        parent::__construct("Autoclicker", "A", "Checks for a high amount of CPS", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data) : void{
        if((($packet instanceof InventoryTransactionPacket && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) || ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE)) && $data->runClickChecks){
            if($data->cps > $this->option("max_cps", 25)) $this->flag($data, ["cps" => round($data->cps, 2)]);
            else $this->reward(0.05);
        }
    }

}