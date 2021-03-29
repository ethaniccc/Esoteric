<?php

namespace ethaniccc\Esoteric\check\combat\range;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\AABB;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;

class RangeA extends Check{

    public function __construct(){
        parent::__construct("Range", "A", "Checks for the raw distance between two entities", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if($packet instanceof InventoryTransactionPacket && !$data->player->isCreative() && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->actionType === InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK && $data->targetEntity !== null){
            $locationData = $data->entityLocationMap->get($packet->trData->entityRuntimeId);
            if($locationData !== null && $locationData->isSynced >= 3){
                $location = $locationData->lastLocation;
                $AABB = AABB::fromPosition($location)->expand(0.1, 0.1, 0.1);
                $distance = $AABB->distanceFromVector($packet->trData->playerPos);
                if($distance > 3.0425){
                    if(++$this->buffer > 1){
                        $this->flag($data, [
                            "dist" => round($distance, 3),
                            "buff" => round($this->buffer, 2)
                        ]);
                        $this->buffer = min($this->buffer, 3);
                    }
                } else {
                    $this->buffer = max($this->buffer - 0.05, 0);
                    $this->reward(0.005);
                }
            }
        }
    }

}