<?php

namespace ethaniccc\Esoteric\check\combat\autoclicker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use function max;
use function min;
use function round;

class AutoClickerA extends Check{

	public function __construct(){
		parent::__construct("Autoclicker", "A", "Checks if the player's cps goes beyond a threshold", false);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data) : void{
		if((($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) || ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEvent::ATTACK_NODAMAGE)) && $data->runClickChecks){
			if($data->cps > $this->option("max_cps", 21)){
				if(++$this->buffer >= 2){
					$this->flag($data, ["cps" => round($data->cps, 2)]);
				}
				$this->buffer = min($this->buffer, 4);
			}else{
				$this->buffer = max($this->buffer - 0.25, 0);
			}
		}
	}

}