<?php

namespace ethaniccc\Esoteric\check\combat\killaura;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\utils\AABB;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use function count;
use function in_array;

/**
 * Class KillAuraB
 * @package ethaniccc\Esoteric\check\combat\killaura
 */
class KillAuraB extends Check {

	/**
	 * @var array
	 */
	private $entities = [];

	/**
	 * KillAuraB constructor.
	 */
	public function __construct() {
		parent::__construct("Killaura", "B", "Checks if the player hits too many entities in an instance", false);
	}

	/**
	 * @param DataPacket $packet
	 * @param PlayerData $data
	 */
	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket) {
			$trData = $packet->trData;
			if ($trData instanceof UseItemOnEntityTransactionData && $trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK) {
				if (!in_array($trData->getEntityRuntimeId(), $this->entities, true)) {
					$this->entities[] = $trData->getEntityRuntimeId();
				}
			}
		} elseif ($packet instanceof PlayerAuthInputPacket) {
			if (count($this->entities) > 1) {
				$lastAABB = null;
				$collides = false;
				foreach ($this->entities as $entityID) {
					$locationData = $data->entityLocationMap->get($entityID);
					if ($locationData === null) {
						continue;
					}
					$AABB = AABB::fromPosition($locationData->lastLocation)->expandedCopy(0.3, 0.3, 0.3);
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
			$this->debug($data, "ent=" . count($this->entities));
			$this->entities = [];
		}
	}

}