<?php

namespace ethaniccc\Esoteric\check\combat\range;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementCapture;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\MathUtils;
use ethaniccc\Esoteric\utils\Ray;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\FlameParticle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use function max;
use function min;
use function round;

class RangeA extends Check {

	private $waiting = false;
	private $secondaryBuffer = 0;

	public function __construct() {
		parent::__construct("Range", "A", "Checking if the player's attack range exceeds a certain limit", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK && in_array($data->gamemode, [GameMode::SURVIVAL, GameMode::ADVENTURE])) {
			$this->waiting = true;
		} elseif ($packet instanceof PlayerAuthInputPacket && $this->waiting) {
			$locationData = $data->entityLocationMap->get($data->target);
			if ($locationData !== null) {
				if ($locationData->isSynced <= 30 || $data->ticksSinceTeleport <= 10) {
					return;
				}
				$AABB = AABB::fromPosition($locationData->lastLocation, $locationData->hitboxWidth + 0.1001, $locationData->hitboxHeight + 0.1001);
				$rawDistance = $AABB->distanceFromVector($data->attackPos);
				if ($rawDistance > $this->option("max_raw", 3.05)) {
					$flagged = true;
					if (++$this->buffer >= 3) {
						$this->flag($data, ["dist" => round($rawDistance, 3), "type" => "raw"]);
						$this->buffer = min($this->buffer, 4.5);
					}
				} else {
					$this->buffer = max($this->buffer - 0.04, 0);
				}
				$this->debug($data, "rD=$rawDistance buff={$this->buffer}");
				if ($packet->getInputMode() !== InputMode::TOUCHSCREEN /* && $locationData->isHuman */ && !$data->boundingBox->intersectsWith($AABB)) { // TODO: Solve SetActorMotion location interpolation stuff
					$currentDirectionVector = $data->directionVector;
					$previousDirectionVector = MathUtils::directionVectorFromValues($data->previousYaw, $data->previousPitch);
					$midDirectionVector = $previousDirectionVector->add($currentDirectionVector->subtract($previousDirectionVector)->divide(2));
					$pos1 = $data->attackPos->add($previousDirectionVector->multiply(3));
					$pos2 = $data->attackPos->add($midDirectionVector->multiply(3));
					$pos3 = $data->attackPos->add($currentDirectionVector->multiply(3));
					// TODO: Thread raycasts - as this will have a significant performance impact if done on the main thread.
					// However, PHP threading is actually a meme and at this point I should just move to Cloudburst so I can abuse
					// SingleThreadedExecutors.
					$results = [
						$AABB->calculateIntercept($data->attackPos, $pos1),
						$AABB->calculateIntercept($data->attackPos, $pos2),
						$AABB->calculateIntercept($data->attackPos, $pos3)
					];
					if (in_array(null, $results)) {
						$this->flag($data, ["type" => "no-intersection"]);
					} else {
						$this->reward(0.005);
					}
				}
				if (!isset($flagged)) {
					$this->reward(0.004);
				}
			}
			$this->waiting = false;
		}
	}

}