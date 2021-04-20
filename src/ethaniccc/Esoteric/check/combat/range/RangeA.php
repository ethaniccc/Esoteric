<?php

namespace ethaniccc\Esoteric\check\combat\range;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\AABB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;

class RangeA extends Check {

	private $waiting = false;

	public function __construct() {
		parent::__construct("Range", "A", "Checking if the player's attack range exceeds a certain limit", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK && in_array($data->gamemode, [GameMode::SURVIVAL, GameMode::ADVENTURE])) {
			$this->waiting = true;
		} elseif ($packet instanceof MovePlayerPacket && $this->waiting) {
			if ($data->currentTick - $data->attackTick <= 2) {
				$locationData = $data->entityLocationMap->get($data->target);
				if ($locationData !== null) {
					if ($locationData->isSynced <= 10) {
						return;
					}
					$rawDistance = 69;
					$locationData->history->iterate(function (Vector3 $location) use (&$rawDistance, $data): void {
						$rawDistance = min(AABB::fromPosition($location)->expand(0.1, 0.1, 0.1)->distanceFromVector($data->attackPos), $rawDistance);
					});
					if ($rawDistance === 69) {
						return;
					}
					if ($data->isMobile) {
						if ($rawDistance > $this->option("max_raw", 3.05)) {
							if (++$this->buffer >= 3) {
								$this->flag($data, ["dist" => round($rawDistance, 3), "type" => "raw"]);
							}
						} else {
							$this->buffer = max($this->buffer - 0.05, 0);
						}
					} else {
						$raycastDistance = 69;
						$locationData->history->iterate(function (Vector3 $location) use (&$raycastDistance, $data): void {
							$AABB = AABB::fromPosition($location)->expand(0.1, 0.1, 0.1);
							if ($AABB->isVectorInside($data->attackPos)) {
								$raycastDistance = 0;
							} else {
								$intersection = $AABB->calculateIntercept($data->attackPos, $data->attackPos->add($data->directionVector->multiply(20)));
								if ($intersection !== null) {
									$raycastDistance = min($intersection->getHitVector()->distance($data->attackPos), $raycastDistance);
								}
							}
						});
						if ($raycastDistance === 69) {
							return;
						}
						if ($raycastDistance > 3 && $rawDistance > 2.7) {
							if (++$this->buffer >= 2.5) {
								$this->flag($data, ["dist" => round($raycastDistance, 3), "type" => "raycast"]);
							}
						} else {
							$this->buffer = max($this->buffer - 0.03, 0);
							$this->reward(0.0035);
						}
					}
				}
			}
			$this->waiting = false;
		}
	}

}