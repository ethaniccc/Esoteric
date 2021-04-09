<?php

namespace ethaniccc\Esoteric\check\combat\range;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\Ray;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class RangeA extends Check {

	private $waiting = false;
	private $hits = 0;

	public function __construct() {
		parent::__construct("Range", "A", "Checking if the player's attack range exceeds a certain limit", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->actionType === InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK && !$data->isMobile) {
			$this->waiting = true;
		} elseif ($packet instanceof MovePlayerPacket && $this->waiting) {
			if ($data->currentTick - $data->attackTick <= 1) {
				$locationData = $data->entityLocationMap->get($data->target);
				if ($locationData !== null) {
					$ray = new Ray($data->attackPos, $data->directionVector);
					$distance = 69;
					$locationData->history->iterate(function (Vector3 $location) use (&$distance, $ray): void {
						$AABB = AABB::fromPosition($location)->expand(0.1, 0.1, 0.1);
						$intersection = $AABB->calculateIntercept($ray->getOrigin(), $ray->traverse(20));
						if ($intersection !== null) {
							$AABB->isVectorInside($ray->getOrigin()) ? $distance = 0 : $distance = min($intersection->getHitVector()->distance($ray->getOrigin()), $distance);
						}
					});

					if ($distance > $this->option("max_reach", 3)) {
						if (++$this->buffer >= 1.2) {
							$this->flag($data, ["dist" => round($distance, 4)]);
						}
					} else {
						$this->buffer = max($this->buffer - 0.0125, 0);
						$this->reward(0.0075);
					}

					/*if($distance !== -69){
						$roundedVector = $locationData->currentLocation->round(6);
						$data->player->sendMessage($distance > 3.001 ? TextFormat::RED . "dist=$distance buff={$this->buffer} x={$roundedVector->x} y={$roundedVector->y} z={$roundedVector->z}" : "dist=$distance buff={$this->buffer} x={$roundedVector->x} y={$roundedVector->y} z={$roundedVector->z}");
					}*/
				}
			}
			$this->waiting = false;
		}
	}

}