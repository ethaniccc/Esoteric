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

class RangeB extends Check {

	private $waiting = false;

	public function __construct() {
		parent::__construct("Range", "B", "Checks if the player is looking at the entity whilst attacking", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->getActionType() === InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK && !$data->isMobile) {
			$this->waiting = true;
		} elseif ($packet instanceof MovePlayerPacket && $this->waiting) {
			if ($data->currentTick - $data->attackTick <= 1) {
				$locationData = $data->entityLocationMap->get($data->target);
				if ($locationData !== null) {
					$ray = new Ray($data->attackPos, $data->directionVector);
					$hasCollision = false;
					$locationData->history->iterate(function (Vector3 $location) use (&$hasCollision, $ray): void {
						if (!$hasCollision) {
							$hasCollision = AABB::fromPosition($location)->expand(0.1, 0.1, 0.1)->calculateIntercept($ray->getOrigin(), $ray->traverse(20)) !== null;
						}
					});
					if (!$hasCollision) {
						if (++$this->buffer >= 10) {
							$this->flag($data);
						}
					} else {
						$this->buffer = max($this->buffer - 1.5, 0);
					}
				}
			}
			$this->waiting = false;
		}
	}

}