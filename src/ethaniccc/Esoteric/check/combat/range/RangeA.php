<?php

namespace ethaniccc\Esoteric\check\combat\range;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\Ray;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;

class RangeA extends Check {

	private $waiting = false;

	public function __construct() {
		parent::__construct("Range", "A", "Checking if the player's attack range exceeds a certain limit", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK) {
			$this->waiting = true;
		} elseif ($packet instanceof MovePlayerPacket && $this->waiting) {
			if ($data->currentTick - $data->attackTick <= 2) {
				$locationData = $data->entityLocationMap->get($data->target);
				if ($locationData !== null) {
					$distance = 69;
					$locationData->history->iterate(function (Vector3 $location) use (&$distance, $data): void {
						$distance = min(AABB::fromPosition($location)->expand(0.1, 0.1, 0.1)->distanceFromVector($data->attackPos), $distance);
					});
					if ($distance !== 69 && $distance > $this->option("max_reach", 3.1)) {
						if (++$this->buffer >= 3) {
							$this->flag($data, ["dist" => round($distance, 4)]);
						}
					} else {
						$this->buffer = max($this->buffer - 0.01, 0);
						$this->reward(0.0075);
					}
					/* if (!$data->isMobile) {
						$ray = new Ray($data->attackPos, $data->directionVector);
						$distance = 69;
						$locationData->history->iterate(function (Vector3 $location) use (&$distance, $ray): void {
							$AABB = AABB::fromPosition($location)->expand(0.10325, 0.10325, 0.10325);
							$intersection = $AABB->calculateIntercept($ray->getOrigin(), $ray->traverse(20));
							if ($intersection !== null) {
								$AABB->isVectorInside($ray->getOrigin()) ? $distance = 0 : $distance = min($intersection->getHitVector()->distance($ray->getOrigin()), $distance);
							}
						});

						if ($distance > $this->option("max_reach", 3.1) && $distance !== 69) {
							if (++$this->buffer >= 3) {
								$this->flag($data, ["dist" => round($distance, 4), "type" => "raycast"]);
							}
						} else {
							$this->buffer = max($this->buffer - 0.04, 0);
							$this->reward(0.0075);
						}
					} */
				}
			}
			$this->waiting = false;
		}
	}

}