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
    /** @var callable[] */
    public $await = [];
    /** @var int */
    public $lastSendTick = 0;
    /** @var int */
    public $tickDiff = 1;

    public function __construct(PlayerData &$data){
        $task = new ClosureTask(function(int $currentTick) use(&$data, &$task) : void{
            if(count($this->needSend) > 0){
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
                $this->tickDiff = Server::getInstance()->getTick() - $this->lastSendTick;
                $this->lastSendTick = 0;
            } elseif($data->player->isClosed()){
                Esoteric::getInstance()->getPlugin()->getScheduler()->cancelTask($task->getTaskId());
            }
        });
        Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleRepeatingTask($task, 1);
    }

    function add(Vector3 $location, int $entityRuntimeId) : void{
        $this->needSend[$entityRuntimeId] = $location;
        if($this->lastSendTick === 0) $this->lastSendTick = Server::getInstance()->getTick();
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
                $locationData->isSynced = true;
            }
        }
        $await = $this->await[0] ?? null;
        array_shift($this->await);
        if($await !== null) foreach($await as $callable) $callable($this);
    }

    function get(int $entityRuntimeId) : ?LocationData{
        return $this->locations[$entityRuntimeId] ?? null;
    }

    function await(callable $callable, int $wait = 1) : void{
        $key = $wait - 1;
        if($key < 0) $key = 0;
        foreach(range(0, $key) as $k) if(!isset($this->await[$k])) $this->await[$k] = [];
        $this->await[$key][] = $callable;
    }

    function remove(int $entityRuntimeId) : void{
        $this->removed[] = $entityRuntimeId;
    }

}