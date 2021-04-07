<?php

namespace ethaniccc\Esoteric\check\combat\range;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\Ray;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class RangeB extends Check {

	private $waiting = false;

	public function __construct() {
		parent::__construct("Range", "B", "Checks if the player is looking at the entity whilst attacking", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->actionType === InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK && !$data->isMobile) {
			$this->waiting = true;
		} elseif ($packet instanceof MovePlayerPacket && $this->waiting) {
			if ($data->currentTick - $data->attackTick <= 1) {
				$locationData = $data->entityLocationMap->get($data->target);
				if ($locationData !== null) {
					$ray = new Ray($data->attackPos, $data->directionVector);
					$AABB = AABB::fromPosition($locationData->lastLocation)->expand(0.1, 0.1, 0.1);
					$intersection = $AABB->calculateIntercept($ray->getOrigin(), $ray->traverse(20));
					$distance = $intersection === null ? -69 : ($AABB->isVectorInside($ray->getOrigin()) ? 0 : $intersection->getHitVector()->distance($ray->getOrigin()));
					if ($distance === -69 && $data->ticksSinceTeleport >= 5 && $locationData->isSynced >= 5) {
						// no intersection
						if (++$this->buffer >= 4) {
							$this->flag($data, ["buff" => round($this->buffer, 2)]);
							$this->buffer = min($this->buffer, 8);
						}
					} else {
						$this->buffer = max($this->buffer - 0.75, 0);
					}
				}
			}
			$this->waiting = false;
		}
	}

}