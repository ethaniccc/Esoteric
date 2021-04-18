<?php

namespace ethaniccc\Esoteric\check\combat\range;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\MathUtils;
use ethaniccc\Esoteric\utils\Ray;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;

class RangeB extends Check {

	private $waiting = false;

	public function __construct() {
		parent::__construct("Range", "B", "Checks if the player is looking at the entity whilst attacking", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK && !$data->isMobile) {
			$this->waiting = true;
		} elseif ($packet instanceof MovePlayerPacket && $this->waiting) {
			if ($data->currentTick - $data->attackTick <= 1) {
				$locationData = $data->entityLocationMap->get($data->target);
				if ($locationData !== null) {

				}
			}
			$this->waiting = false;
		}
	}

}