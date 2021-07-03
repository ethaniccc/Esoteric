<?php

namespace ethaniccc\Esoteric\check\combat\killaura;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\utils\AABB;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use function count;
use function in_array;
use function is_null;

class KillAuraB extends Check {

	private array $entities = [];

	public function __construct() {
		parent::__construct("Killaura", "B", "Checks if the player hits too many entities in an instance", false);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket) {
			$trData = $packet->trData;
			if ($trData instanceof UseItemOnEntityTransactionData && $trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK) {
				if (!in_array($trData->getEntityRuntimeId(), $this->entities)) {
					$this->entities[] = $trData->getEntityRuntimeId();
				}
			}
		} elseif ($packet instanceof PlayerAuthInputPacket) {
			if (count($this->entities) > 1) {
				$lastAABB = null;
				$collides = false;
				foreach ($this->entities as $entityID) {
					$locationData = $data->entityLocationMap->get($entityID);
					if (is_null($locationData)) {
						continue;
					}
					$AABB = AABB::fromPosition($locationData->lastLocation)->expandedCopy(0.2, 0.2, 0.2);
					if ($lastAABB !== null) {
						$collides = $AABB->intersectsWith($lastAABB);
						if ($collides) {
							break;
						}
					}
					$lastAABB = $AABB;
				}
				if (!$collides) {
					$this->flag($data, ["entities" => count($this->entities)]);
				}
			}
			$this->entities = [];
		}
	}

}