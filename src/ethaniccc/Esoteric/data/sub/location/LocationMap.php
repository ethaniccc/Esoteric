<?php

namespace ethaniccc\Esoteric\data\sub\location;

use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\WorldManager;
use function count;

/**
 * Class LocationMap
 * @package ethaniccc\Esoteric\data\sub
 * LocationMap is a class that stores estimated client side locations in an array. This will be used in some combat checks.
 */
final class LocationMap {

	/** @var LocationData[] - Estimated client-sided locations */
	public array $locations = [];
	/** @var Position[] */
	public array $needSendArray = [];
	public WorldManager $worldManager;

	public function __construct() {
		$this->worldManager = Server::getInstance()->getWorldManager();
	}

	function addEntity(MovePlayerPacket|MoveActorAbsolutePacket $packet): void {
		if ($packet instanceof MovePlayerPacket && $packet->mode !== MovePlayerPacket::MODE_NORMAL) {
			$packet->mode = MovePlayerPacket::MODE_RESET;
			$data = $this->locations[$packet->entityRuntimeId] ?? null;
			if ($data !== null) {
				$data->isSynced = 0;
				$data->newPosRotationIncrements = 1;
			}
		}
		$entity = $this->worldManager->findEntity($packet->entityRuntimeId);
		if ($entity !== null) {
			$location = $packet->position->subtract(0, $entity->getOffsetPosition($entity->getPosition())->y - $entity->getPosition()->y, 0);
			if (isset($this->locations[$entity->getId()])) {
				$this->needSendArray[$packet->entityRuntimeId] = $location;
			} else {
				$this->locations[$entity->getId()] = new LocationData($entity->getId(), $entity instanceof Human, Location::fromObject($location, $entity->getWorld()));
			}
		}
	}


	function removeEntity(int $entityRuntimeId): void {
		unset($this->locations[$entityRuntimeId]);
	}

	function send(PlayerData $data): void {
		if (count($this->needSendArray) === 0 || !$data->loggedIn) return;
		$locations = $this->needSendArray;
		$this->needSendArray = [];
		$data->networkStackLatencyHandler->queue($data, function () use ($locations): void {
			foreach ($locations as $entityRuntimeId => $location) {
				if (isset($this->locations[$entityRuntimeId])) {
					$locationData = $this->locations[$entityRuntimeId];
					$locationData->newPosRotationIncrements = 3;
					$locationData->receivedLocation = $location;
				}
			}
		});
	}

	function executeTick(): void {
		foreach ($this->locations as $entityRuntimeId => $locationData) {
			if (($entity = $this->worldManager->findEntity($entityRuntimeId)) === null) {
				// entity go brrt !
				unset($this->locations[$entityRuntimeId]);
				unset($this->needSendArray[$entityRuntimeId]);
			} else {
				if ($locationData->newPosRotationIncrements > 0) {
					$locationData->lastLocation = clone $locationData->currentLocation;
					$locationData->currentLocation->x = ($locationData->currentLocation->x + (($locationData->receivedLocation->x - $locationData->currentLocation->x) / $locationData->newPosRotationIncrements));
					$locationData->currentLocation->y = ($locationData->currentLocation->y + (($locationData->receivedLocation->y - $locationData->currentLocation->y) / $locationData->newPosRotationIncrements));
					$locationData->currentLocation->z = ($locationData->currentLocation->z + (($locationData->receivedLocation->z - $locationData->currentLocation->z) / $locationData->newPosRotationIncrements));
				} elseif ($locationData->newPosRotationIncrements === 0) {
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

	function get(int $entityRuntimeId): ?LocationData {
		return $this->locations[$entityRuntimeId] ?? null;
	}

}