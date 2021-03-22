<?php

namespace ethaniccc\Esoteric\check\combat\range;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;

class RangeA extends Check{

    public function __construct(){
        parent::__construct("Range", "A", "Checks for the raw distance between two entities", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if($packet instanceof InventoryTransactionPacket && !$data->player->isCreative() && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->actionType === InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK && $data->targetEntity !== null){
            $locationData = $data->entityLocationMap->get($packet->trData->entityRuntimeId);
            if($locationData !== null && $locationData->isSynced){
                $location = $locationData->lastLocation;
            }
        }
    }

}