<?php

namespace ethaniccc\Esoteric\data\sub\location;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\listener\NetworkStackLatencyHandler;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

/**
 * Class LocationMap
 * @package ethaniccc\Esoteric\data\sub
 * LocationMap is a class that stores estimated client side locations in an array. This will be used in checks such as Range.
 */
final class LocationMap{

    /** @var LocationData[] - Estimated client-sided locations */
    public $locations = [];
    /** @var Vector3[] - Locations that need sending */
    public $needSend = [];
    /** @var int[] */
    public $removed = [];
    /** @var int */
    public $lastSendTick = 0;

    function add(Vector3 $location, int $entityRuntimeId) : void{
        $this->needSend[$entityRuntimeId] = $location;
        if($this->lastSendTick === 0) $this->lastSendTick = Server::getInstance()->getTick();
    }

    function send(PlayerData $data): void{
        $locations = $this->needSend;
        $this->needSend = [];
        NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($locations) : void{
            foreach($locations as $entityRuntimeId => $location){
                if(!isset($this->locations[$entityRuntimeId])){
                    $locationData = new LocationData();
                    $locationData->entityRuntimeId = $entityRuntimeId;
                    $locationData->newPosRotationIncrements = 3;
                    $locationData->currentLocation = clone $location;
                    $locationData->lastLocation = clone $location;
                    $locationData->receivedLocation = clone $location;
                    $this->locations[$entityRuntimeId] = $locationData;
                } else {
                    $locationData = $this->locations[$entityRuntimeId];
                    $locationData->newPosRotationIncrements = 3;
                    $locationData->receivedLocation = $location;
                }
            }
        });
    }

    function executeTick(PlayerData $data) : void{
        foreach($this->locations as $entityRuntimeId => $locationData){
            if(($entity = Server::getInstance()->findEntity($entityRuntimeId)) === null){
                unset($this->locations[$entityRuntimeId]);
                unset($this->needSend[$entityRuntimeId]);
            } else {
                if($locationData->newPosRotationIncrements > 0){
                    $locationData->lastLocation = clone $locationData->currentLocation;
                    $locationData->currentLocation->x = $locationData->currentLocation->x + (($locationData->receivedLocation->x - $locationData->currentLocation->x) / $locationData->newPosRotationIncrements);
                    $locationData->currentLocation->y = $locationData->currentLocation->y + (($locationData->receivedLocation->y - $locationData->currentLocation->y) / $locationData->newPosRotationIncrements);
                    $locationData->currentLocation->z = $locationData->currentLocation->z + (($locationData->receivedLocation->z - $locationData->currentLocation->z) / $locationData->newPosRotationIncrements);
                } elseif($locationData->newPosRotationIncrements === 0){
                    // don't need to clone all the time... lol
                    $locationData->lastLocation = clone $locationData->currentLocation;
                }
                $locationData->newPosRotationIncrements--;
                $locationData->isSynced++;
            }
        }
    }

    function get(int $entityRuntimeId) : ?LocationData{
        return $this->locations[$entityRuntimeId] ?? null;
    }

    function remove(int $entityRuntimeId) : void{
        $this->removed[] = $entityRuntimeId;
    }

}