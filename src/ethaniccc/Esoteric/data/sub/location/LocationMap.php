<?php

namespace ethaniccc\Esoteric\data\sub\location;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\process\NetworkStackLatencyHandler;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

/**
 * Class LocationMap
 * @package ethaniccc\Esoteric\data\sub
 * LocationMap is a class that stores estimated client side locations in an array. This will be used in some combat checks.
 */
final class LocationMap{

	/** @var LocationData[] - Estimated client-sided locations */
	public array $locations = [];
	/** @var Location[] */
	public array $pendingLocations = [];

	/**
	 * @param MovePlayerPacket|MoveActorAbsolutePacket $packet
	 */
	public function add($packet) : void{
		if($packet instanceof MovePlayerPacket && $packet->mode !== MovePlayerPacket::MODE_NORMAL){
			$packet->mode = MovePlayerPacket::MODE_RESET;
			$data = $this->locations[$packet->actorRuntimeId] ?? null;
			if($data !== null){
				$data->isSynced = 0;
				$data->newPosRotationIncrements = 1;
			}
		}
		$entity = Server::getInstance()->getWorldManager()->findEntity($packet->actorRuntimeId);
		if($entity !== null){
			$location = $packet->position->subtract(0, ($packet instanceof MovePlayerPacket ? 1.62 : 0), 0);
			if(!isset($this->locations[$entity->getId()])){
				$this->locations[$entity->getId()] = new LocationData($entity->getId(), $entity instanceof Player, Location::fromObject($location, $entity->getWorld()), 0.3, 1.8);
			}
			$this->pendingLocations[$packet->actorRuntimeId] = $location;
		}
	}

	public function send(PlayerData $data) : void{
		if(!$data->loggedIn){
			return;
		}
		$locations = $this->pendingLocations;
		$this->pendingLocations = [];
		NetworkStackLatencyHandler::getInstance()->queue($data, function(int $timestamp) use ($locations, $data) : void{
			foreach($locations as $entityRuntimeId => $location){
				$locationData = $this->locations[$entityRuntimeId] ?? null;
				if($locationData === null)
					continue;
				$locationData->newPosRotationIncrements = 3;
				$locationData->receivedLocation = Position::fromObject($location, $data->player->getWorld());
			}
		});
	}

	public function executeTick() : void{
		foreach($this->locations as $entityRuntimeId => $locationData){
			if(($entity = Server::getInstance()->getWorldManager()->findEntity($entityRuntimeId)) === null){
				// entity go brrt !
				unset($this->locations[$entityRuntimeId], $this->pendingLocations[$entityRuntimeId]);
			}else{
				if($locationData->newPosRotationIncrements > 0){
					$locationData->lastLocation = clone $locationData->currentLocation;
					$locationData->currentLocation->x += (($locationData->receivedLocation->x - $locationData->currentLocation->x) / $locationData->newPosRotationIncrements);
					$locationData->currentLocation->y += (($locationData->receivedLocation->y - $locationData->currentLocation->y) / $locationData->newPosRotationIncrements);
					$locationData->currentLocation->z += (($locationData->receivedLocation->z - $locationData->currentLocation->z) / $locationData->newPosRotationIncrements);
					$locationData->currentLocation->world = $entity->getWorld();
				}elseif($locationData->newPosRotationIncrements === 0){
					// don't need to clone all the time... lol
					$locationData->lastLocation = clone $locationData->currentLocation;
				}
				$bb = $entity->getBoundingBox();
				$locationData->hitboxWidth = ($bb->maxX - $bb->minX) * 0.5;
				$locationData->hitboxHeight = $bb->maxY - $bb->minY;
				$locationData->newPosRotationIncrements--;
				$locationData->isSynced++;
			}
		}
	}

	public function get(int $entityRuntimeId) : ?LocationData{
		return $this->locations[$entityRuntimeId] ?? null;
	}

}