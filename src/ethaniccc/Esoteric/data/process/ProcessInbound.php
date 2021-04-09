<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\LevelUtils;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\block\Block;
use pocketmine\block\Cobweb;
use pocketmine\block\Ladder;
use pocketmine\block\Liquid;
use pocketmine\block\UnknownBlock;
use pocketmine\block\Vine;
use pocketmine\entity\Attribute;
use pocketmine\entity\Effect;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;

final class ProcessInbound {

	/** @var Vector3[] */
	public $blockPlaceVectors = [];
	/** @var Vector3 */
	public $knockbackMotion;

	public function execute(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket) {
			$location = Location::fromObject($packet->position->subtract(0, 1.62, 0), $data->player->getLevel(), $packet->yaw, $packet->pitch);
			$data->teleported = false;
			$data->hasMovementSuppressed = false;
			$data->boundingBox = AABB::fromPosition($location->asVector3());
			$data->lastLocation = clone $data->currentLocation;
			$data->currentLocation = $location;
			$data->lastMoveDelta = $data->currentMoveDelta;
			$data->currentMoveDelta = $data->currentLocation->subtract($data->lastLocation)->asVector3();
			$data->previousYaw = $data->currentYaw;
			$data->previousPitch = $data->currentPitch;
			$data->currentYaw = $packet->yaw;
			$data->currentPitch = $packet->pitch;
			$data->lastYawDelta = $data->currentYawDelta;
			$data->lastPitchDelta = $data->currentPitchDelta;
			$data->currentYawDelta = abs(abs($data->currentYaw) - abs($data->previousYaw));
			$data->currentPitchDelta = abs(abs($data->currentPitch) - abs($data->previousPitch));
			$data->directionVector = MathUtils::directionVectorFromValues($data->currentYaw, $data->currentPitch);
			$expectedMoveY = ($data->lastMoveDelta->y - MovementConstants::Y_SUBTRACTION) * MovementConstants::Y_MULTIPLICATION;
			$actualMoveY = $data->currentMoveDelta->y;
			$flag1 = abs($expectedMoveY - $actualMoveY) > 0.001;
			$flag2 = $expectedMoveY < 0;
			$data->hasBlockAbove = $flag1 && $expectedMoveY > 0;
			$data->isCollidedVertically = $flag1;
			$data->onGround = $packet->onGround;
			$AABBCollision = count($location->getLevel()->getCollisionBlocks($data->boundingBox->expandedCopy(0.5, 0.2, 0.5), true)) !== 0;
			$data->expectedOnGround = $AABBCollision;
			$data->isCollidedHorizontally = count($location->getLevel()->getCollisionBlocks($data->boundingBox->expand(0.5, -0.05, 0.5), true)) !== 0;

			if ($data->onGround) {
				++$data->onGroundTicks;
				$data->offGroundTicks = 0;
				$data->lastOnGroundLocation = clone $data->currentLocation;
			} else {
				++$data->offGroundTicks;
				$data->onGroundTicks = 0;
			}
			++$data->ticksSinceMotion;
			if ($data->ticksSinceTeleport <= 2) {
				$data->teleported = true;
				if ($data->ticksSinceTeleport === 0) {
					$data->currentMoveDelta = clone PlayerData::$ZERO_VECTOR;
				}
			} else {
				$data->teleported = false;
			}
			++$data->ticksSinceTeleport;
			if ($data->isFlying) {
				$data->ticksSinceFlight = 0;
			} else {
				++$data->ticksSinceFlight;
			}
			++$data->ticksSinceJump;

			$liquids = 0;
			$climbable = 0;
			$cobweb = 0;

			$data->moveForward = 0.0;
			$data->moveStrafe = 0.0;

			// here we want to predict the moveForward and moveStrafing values of the player
			// reference: https://www.spigotmc.org/threads/player-moveforward-movestrafe-aispeed.441073/#post-3819915
			if ($data->ticksSinceMotion === 1) {
				$this->knockbackMotion = clone $data->motion;
			}

			// how is 0.91 more effective here than 0.98 (assumed normal friction??)
			if ($data->onGround) {
				$friction = 0.91 * $data->player->getLevel()->getBlockAt($data->lastLocation->x, $data->lastLocation->y - 1, $data->lastLocation->z, false, false)->getFrictionFactor();
			} else {
				$friction = 0.91;
			}

			$currVelocity = new Vector3($data->currentMoveDelta->x, 0, $data->currentMoveDelta->z);
			$prevVelocity = new Vector3($data->lastMoveDelta->x, 0, $data->lastMoveDelta->z);

			if ($this->knockbackMotion !== null) {
				$prevVelocity = $this->knockbackMotion;
			}

			if (abs($prevVelocity->x * $friction) < 0.005) {
				$prevVelocity->x = 0;
			}
			if (abs($prevVelocity->z * $friction) < 0.005) {
				$prevVelocity->z = 0;
			}

			$currVelocity->x /= $friction;
			$currVelocity->z /= $friction;
			$currVelocity->x -= $prevVelocity->x;
			$currVelocity->z -= $prevVelocity->z;
			$yawVec = MathUtils::directionVectorFromValues($data->currentYaw, 0);

			// you're actually pressing a WASD key
			if ($currVelocity->lengthSquared() >= 0.0002) {
				$vectorDir = $currVelocity->cross($yawVec)->dot(new Vector3(0, 1, 0)) >= 0;
				$angle = ($vectorDir ? 1 : -1) * MathUtils::vectorAngle($currVelocity, $yawVec);
				$deg = round(rad2deg($angle));
				if (abs($deg) < 45) {
					$data->moveForward = 1.0;
				} elseif (abs(abs($deg) - 180) <= 10) {
					$data->moveForward = -1.0;
				} elseif (abs($deg - 45) <= 45) {
					$data->moveForward = 1.0;
					$data->moveStrafe = 1.0;
				} elseif (abs($deg + 45) <= 45) {
					$data->moveForward = 1.0;
					$data->moveStrafe = -1.0;
				} elseif (abs($deg - 135) <= 45) {
					$data->moveForward = -1.0;
					$data->moveStrafe = 1.0;
				} elseif (abs($deg + 135) <= 45) {
					$data->moveForward = -1.0;
					$data->moveStrafe = -1.0;
				}
			}

			if (abs($data->moveForward) > 0 && abs($data->moveStrafe) > 0) {
				$data->moveForward *= 0.8;
				$data->moveStrafe *= 0.8;
			}

			if ($data->isSneaking) {
				$var2 = MathUtils::getLiteralFloat(0.3);
				$data->moveForward *= $var2;
				$data->moveStrafe *= $var2;
			}

			$var3 = MathUtils::getLiteralFloat(0.98);
			$data->moveForward *= $var3;
			$data->moveStrafe *= $var3;

			$this->knockbackMotion = null;

			foreach (LevelUtils::checkBlocksInAABB($data->boundingBox->expandedCopy(0.5, 0, 0.5), $data->player->getLevel(), LevelUtils::SEARCH_TRANSPARENT) as $block) {
				/** @var Block $block */
				if ($block instanceof Liquid)
					$liquids++; elseif ($block instanceof Cobweb)
					$cobweb++;
				elseif ($block instanceof Ladder || $block instanceof Vine)
					$climbable++;
			}

			if ($liquids > 0)
				$data->ticksSinceInLiquid = 0; else ++$data->ticksSinceInLiquid;

			if ($cobweb > 0)
				$data->ticksSinceInCobweb = 0; else ++$data->ticksSinceInCobweb;

			if ($climbable > 0)
				$data->ticksSinceInClimbable = 0; else ++$data->ticksSinceInClimbable;

			$data->movementSpeed = $data->player->getAttributeMap()->getAttribute(Attribute::MOVEMENT_SPEED)->getValue();

			foreach ($data->effects as $effectData) {
				switch ($effectData->effectId) {
					case Effect::JUMP_BOOST:
						$data->jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION + ($effectData->amplifier / 10);
						break;
				}
			}

			if (!isset($data->effects[Effect::JUMP])) {
				$data->jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;
			}

			/**
			 * Checks if there is a possible ghost block that the player is standing on. If there is a ghost block that the player is standing on,
			 * we should remove it to prevent possible false-flags with a GroundSpoof check.
			 */

			$possibleGhostBlock = $data->onGround && !$AABBCollision;
			if ($possibleGhostBlock) {
				foreach ($this->blockPlaceVectors as $blockVector) {
					if (AABB::fromPosition($location)->expand(3, 3, 3)->isVectorInside($blockVector)) {
						$data->expectedOnGround = true;
						NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data, $blockVector): void {
							$pk = new UpdateBlockPacket();
							$pk->x = $blockVector->x;
							$pk->y = $blockVector->y;
							$pk->z = $blockVector->z;
							$pk->blockRuntimeId = 134;
							$pk->flags = UpdateBlockPacket::FLAG_ALL_PRIORITY;
							$pk->dataLayerId = UpdateBlockPacket::DATA_LAYER_NORMAL;
							$data->player->dataPacket($pk);
							NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($blockVector): void {
								foreach ($this->blockPlaceVectors as $key => $vector) {
									if ($vector->equals($blockVector)) {
										unset($this->blockPlaceVectors[$key]);
										break;
									}
								}
							});
						});
					}
				}
			}
		} elseif ($packet instanceof InventoryTransactionPacket) {
			switch ($packet->transactionType) {
				case InventoryTransactionPacket::TYPE_USE_ITEM:
					switch ($packet->trData->actionType) {
						case InventoryTransactionPacket::USE_ITEM_ACTION_CLICK_BLOCK:
							$clickedBlockPos = new Vector3($packet->trData->x, $packet->trData->y, $packet->trData->z);
							$newBlockPos = $clickedBlockPos->getSide($packet->trData->face);
							$block = $packet->trData->itemInHand->getBlock();
							if ($packet->trData->itemInHand->getId() < 0) {
								$block = new UnknownBlock($packet->trData->itemInHand->getId(), 0);
							}
							if (($block->canBePlaced() || $block instanceof UnknownBlock) && !in_array($newBlockPos, $this->blockPlaceVectors)) {
								$this->blockPlaceVectors[] = $newBlockPos;
							}
							break;
					}
					break;
				case InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY:
					switch ($packet->trData->actionType) {
						case InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK:
							$data->lastTarget = $data->target;
							$data->target = $packet->trData->entityRuntimeId;
							$data->attackTick = $data->currentTick;
							$data->attackPos = $packet->trData->playerPos;
							break;
					}
					$this->click($data);
					break;
			}
		} elseif ($packet instanceof NetworkStackLatencyPacket) {
			NetworkStackLatencyHandler::execute($data, $packet->timestamp);
		} elseif ($packet instanceof SetLocalPlayerAsInitializedPacket) {
			$data->hasAlerts = $data->player->hasPermission("ac.alerts");
			$data->loggedIn = true;
			$data->gamemode = $data->player->getGamemode();
		} elseif ($packet instanceof AdventureSettingsPacket) {
			$data->isFlying = $packet->getFlag(AdventureSettingsPacket::FLYING);
			$data->hasFlyFlag = $data->isFlying;
		} elseif ($packet instanceof PlayerActionPacket) {
			switch ($packet->action) {
				case PlayerActionPacket::ACTION_START_SPRINT:
					$data->isSprinting = true;
					$data->jumpMovementFactor = MovementConstants::JUMP_MOVE_SPRINT;
					break;
				case PlayerActionPacket::ACTION_STOP_SPRINT:
					$data->isSprinting = false;
					$data->jumpMovementFactor = MovementConstants::JUMP_MOVE_NORMAL;
					break;
				case PlayerActionPacket::ACTION_START_SNEAK:
					$data->isSneaking = true;
					break;
				case PlayerActionPacket::ACTION_STOP_SNEAK:
					$data->isSneaking = false;
					break;
				case PlayerActionPacket::ACTION_JUMP:
					$data->ticksSinceJump = 0;
					break;
			}
		} elseif ($packet instanceof LoginPacket) {
			// ignore modified data other plugins may have put in
			$pk = new LoginPacket($packet->getBuffer());
			$pk->decode();
			$data->protocol = $pk->protocol;
			$data->isMobile = in_array($pk->clientData["DeviceOS"], [DeviceOS::AMAZON, DeviceOS::ANDROID, DeviceOS::IOS]);
		} elseif ($packet instanceof LevelSoundEventPacket) {
			if ($packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE) {
				$this->click($data);
			}
		}
	}

	private function click(PlayerData $data) {
		if (count($data->clickSamples) === 20) {
			$data->clickSamples = [];
			$data->runClickChecks = false;
		}
		$data->clickSamples[] = $data->currentTick - $data->lastClickTick;
		if (count($data->clickSamples) === 20) {
			try {
				$data->cps = 20 / MathUtils::getAverage($data->clickSamples);
				if ($data->cps === 100.0) {
					// ticked once... lol
					$data->isClickDataIsValid = false;
				}
			} catch (\ErrorException $e) {
				$data->cps = INF;
				$data->isClickDataIsValid = false;
			}
			$data->kurtosis = MathUtils::getKurtosis($data->clickSamples);
			$data->skewness = MathUtils::getSkewness($data->clickSamples);
			$data->deviation = MathUtils::getDeviation($data->clickSamples);
			$data->outliers = MathUtils::getOutliers($data->clickSamples);
			$data->variance = MathUtils::getVariance($data->clickSamples);
			$data->runClickChecks = true;
		}
		$data->lastClickTick = $data->currentTick;
	}

}