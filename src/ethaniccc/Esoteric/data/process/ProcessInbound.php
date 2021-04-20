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
use pocketmine\block\Solid;
use pocketmine\block\UnknownBlock;
use pocketmine\block\Vine;
use pocketmine\entity\Attribute;
use pocketmine\entity\Effect;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
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
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\timings\TimingsHandler;

final class ProcessInbound {

	/** @var Block[] */
	public $placedBlocks = [];

	/** @var TimingsHandler */
	public static $timings;
	/** @var TimingsHandler */
	public static $inventoryTransactionTimings;
	/** @var TimingsHandler */
	public static $networkStackLatencyTimings;
	/** @var TimingsHandler */
	public static $clickTimings;

	/** @var TimingsHandler */
	public static $movementTimings;
	/** @var TimingsHandler */
	public static $collisionTimings;

	public function __construct() {
		if (self::$timings === null) {
			self::$timings = new TimingsHandler("Esoteric Inbound Handling");
			self::$movementTimings = new TimingsHandler("Esoteric Movement Handling", self::$timings);
			self::$collisionTimings = new TimingsHandler("Esoteric Movement Collisions", self::$movementTimings);
			self::$inventoryTransactionTimings = new TimingsHandler("Esoteric Transaction Handling", self::$timings);
			self::$networkStackLatencyTimings = new TimingsHandler("Esoteric NetworkStackLatency Handling", self::$timings);
			self::$clickTimings = new TimingsHandler("Esoteric Click Handling", self::$timings);
		}
	}

	public function execute(DataPacket $packet, PlayerData $data): void {
		self::$timings->startTiming();
		if ($packet instanceof MovePlayerPacket) {
			if (!$data->loggedIn) {
				return;
			}
			self::$movementTimings->startTiming();
			$data->lastBlocksBelow = $data->blocksBelow;
			$location = Location::fromObject($packet->position->subtract(0, 1.62, 0), $data->player->getLevel(), $packet->yaw, $packet->pitch);
			$data->inLoadedChunk = $data->chunkSendPosition->distance($data->currentLocation->floor()) <= $data->player->getViewDistance() * 16;
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
			$data->currentYawDelta = abs($data->currentYaw - $data->previousYaw);
			if ($data->currentYawDelta > 180) {
				$data->currentYawDelta = 360 - $data->currentYawDelta;
			}
			$data->currentPitchDelta = abs($data->currentPitch - $data->previousPitch);
			$data->directionVector = MathUtils::directionVectorFromValues($data->currentYaw, $data->currentPitch);
			$expectedMoveY = ($data->lastMoveDelta->y - MovementConstants::Y_SUBTRACTION) * MovementConstants::Y_MULTIPLICATION;
			$actualMoveY = $data->currentMoveDelta->y;
			$flag1 = abs($expectedMoveY - $actualMoveY) > 0.001;
			$flag2 = $expectedMoveY < 0;
			$AABB1 = $data->boundingBox->expandedCopy(0, 0.1, 0);
			$AABB1->minY = $data->boundingBox->maxY - 0.4;
			$data->hasBlockAbove = $flag1 && $expectedMoveY > 0 && abs($expectedMoveY) > 0.005 && count($data->player->getLevel()->getCollisionBlocks($AABB1, true)) !== 0;
			$data->isCollidedVertically = $flag1;
			$data->onGround = $packet->onGround;

			self::$collisionTimings->startTiming();
			// LevelUtils::checkBlocksInAABB() is basically a duplicate of getCollisionBlocks, but in here, it will get all blocks
			// if the block doesn't have an AABB, this assumes a 1x1x1 AABB for that block
			$blocks = LevelUtils::checkBlocksInAABB($data->boundingBox->expandedCopy(0.5, 0.2, 0.5), $location->getLevel(), LevelUtils::SEARCH_ALL, false);
			$data->expectedOnGround = $blocks->valid();
			$data->blocksBelow = [];
			$data->isCollidedHorizontally = false;
			$data->isCollidedVertically = false;
			$liquids = 0;
			$climbable = 0;
			$cobweb = 0;
			foreach ($blocks as $block) {
				/** @var Block $block */
				if (!$data->isCollidedHorizontally) {
					$data->isCollidedHorizontally = $block->collidesWithBB($data->boundingBox) && $block->y > $location->y;
				}
				if (!$data->isCollidedVertically) {
					$data->isCollidedVertically = $block->collidesWithBB($data->boundingBox);
				}
				if ($block->y <= $location->y && $block->collidesWithBB($data->boundingBox)) {
					$data->blocksBelow[] = $block;
				}
				if ($block instanceof Liquid) {
					$liquids++;
				} elseif ($block instanceof Cobweb) {
					$cobweb++;
				} elseif ($block instanceof Ladder || $block instanceof Vine) {
					$climbable++;
				}
			}
			self::$collisionTimings->stopTiming();

			$data->isInVoid = $location->y <= -35;

			if ($data->onGround) {
				++$data->onGroundTicks;
				$data->offGroundTicks = 0;
				$data->lastOnGroundLocation = clone $data->currentLocation;
			} else {
				++$data->offGroundTicks;
				$data->onGroundTicks = 0;
			}
			++$data->ticksSinceMotion;
			if ($data->ticksSinceTeleport <= 1) {
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

			$data->moveForward = 0.0;
			$data->moveStrafe = 0.0;

			/* $knockbackMotion = null;

			// here we want to predict the moveForward and moveStrafing values of the player
			// reference: https://www.spigotmc.org/threads/player-moveforward-movestrafe-aispeed.441073/#post-3819915
			if ($data->ticksSinceMotion === 1) {
				$knockbackMotion = clone $data->motion;
			}

			// how is 0.91 more effective here than 0.98 (assumed normal friction??)
			if ($data->offGroundTicks <= 2) {
				$friction = 0.91 * (($block = $data->player->getLevel()->getBlockAt($data->lastLocation->x, $data->lastLocation->y - 1, $data->lastLocation->z, false, false))->getId() === 0 ? 0.6 : $block->getFrictionFactor());
			} else {
				$friction = 0.91;
			}

			$currVelocity = new Vector3($data->currentMoveDelta->x, 0, $data->currentMoveDelta->z);
			$prevVelocity = new Vector3($data->lastMoveDelta->x, 0, $data->lastMoveDelta->z);

			if ($knockbackMotion !== null) {
				$prevVelocity = $knockbackMotion;
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
			if ($currVelocity->lengthSquared() >= 0.000001) {
				$vectorDir = $currVelocity->cross($yawVec)->dot(new Vector3(0, 1, 0)) >= 0;
				$angle = ($vectorDir ? 1 : -1) * MathUtils::vectorAngle($currVelocity, $yawVec);
				$deg = round(rad2deg($angle));
				if (abs($deg) <= 20) {
					$data->moveForward = 1.0;
				} elseif (abs(abs($deg) - 180) <= 10) {
					$data->moveForward = -1.0;
				} elseif (abs($deg - 45) < 45) {
					$data->moveForward = 1.0;
					$data->moveStrafe = 1.0;
				} elseif (abs($deg + 45) < 45) {
					$data->moveForward = 1.0;
					$data->moveStrafe = -1.0;
				} elseif (abs($deg - 135) < 45) {
					$data->moveForward = -1.0;
					$data->moveStrafe = 1.0;
				} elseif (abs($deg + 135) < 45) {
					$data->moveForward = -1.0;
					$data->moveStrafe = -1.0;
				} elseif (abs($deg - 90) < 45) {
					$data->moveForward = 0.0;
					$data->moveStrafe = 1.0;
				} elseif (abs($deg + 90) < 45) {
					$data->moveForward = 0.0;
					$data->moveStrafe = 1.0;
				}
			}

			if (abs($data->moveForward) > 0 && abs($data->moveStrafe) > 0) {
				$data->moveForward *= 0.7888;
				$data->moveStrafe *= 0.7888;
			}

			if ($data->isSneaking) {
				$var2 = 0.3;
				$data->moveForward *= $var2;
				$data->moveStrafe *= $var2;
			}

			$var3 = 0.98;
			$data->moveForward *= $var3;
			$data->moveStrafe *= $var3; */

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

			// TODO: There's a stupid bug where setting a block with UpdateBlockPacket won't do anything, make future attempts to fix this BS.

			if ($data->onGround) {
				foreach ($this->placedBlocks as $blockVector) {
					if ($data->boundingBox->expandedCopy(4, 4, 4)->isVectorInside($blockVector->asVector3())) {
						$data->expectedOnGround = true;
						$data->blocksBelow[] = $blockVector;
						$realBlock = $data->player->getLevel()->getBlock($blockVector, false, false);
						NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data, $realBlock): void {
							$pk = new UpdateBlockPacket();
							$pk->x = $realBlock->x;
							$pk->y = $realBlock->y;
							$pk->z = $realBlock->z;
							$pk->blockRuntimeId = RuntimeBlockMapping::toStaticRuntimeId($realBlock->getId(), $realBlock->getDamage());
							$pk->flags = UpdateBlockPacket::FLAG_ALL_PRIORITY;
							$pk->dataLayerId = $realBlock instanceof Liquid ? UpdateBlockPacket::DATA_LAYER_LIQUID : UpdateBlockPacket::DATA_LAYER_NORMAL;
							$data->player->batchDataPacket($pk);
							NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($realBlock): void {
								foreach ($this->placedBlocks as $key => $vector) {
									if ($vector->equals($realBlock->asVector3())) {
										unset($this->placedBlocks[$key]);
										break;
									}
								}
							});
						});
					}
				}
			}
			self::$movementTimings->stopTiming();
		} elseif ($packet instanceof InventoryTransactionPacket) {
			self::$inventoryTransactionTimings->startTiming();
			$trData = $packet->trData;
			switch ($trData->getTypeId()) {
				case InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY:
					/** @var UseItemOnEntityTransactionData $trData */
					if ($trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK) {
						$data->lastTarget = $data->target;
						$data->target = $trData->getEntityRuntimeId();
						$data->attackTick = $data->currentTick;
						$data->attackPos = $trData->getPlayerPos();
					}
					break;
				case InventoryTransactionPacket::TYPE_USE_ITEM:
					if ($trData->getActionType() === UseItemTransactionData::ACTION_CLICK_BLOCK) {
						$clickedBlockPos = $trData->getBlockPos();
						$newBlockPos = $clickedBlockPos->getSide($trData->getFace());
						$blockToReplace = $data->player->getLevel()->getBlock($newBlockPos, false, false);
						if ($blockToReplace->canBeReplaced()) {
							$block = $trData->getItemInHand()->getItemStack()->getBlock();
							if ($trData->getItemInHand()->getItemStack()->getId() < 0) {
								$block = new UnknownBlock($trData->getItemInHand()->getItemStack()->getId(), 0);
							}
							foreach ($this->placedBlocks as $other) {
								if ($other->asVector3()->equals($blockToReplace->asVector3())) {
									break;
								}
							}
							if (($block->canBePlaced() || $block instanceof UnknownBlock)) {
								$block->position($blockToReplace->asPosition());
								$this->placedBlocks[] = clone $block;
							}
						}
					}
					break;
			}
			self::$inventoryTransactionTimings->stopTiming();
		} elseif ($packet instanceof NetworkStackLatencyPacket) {
			self::$networkStackLatencyTimings->startTiming();
			NetworkStackLatencyHandler::execute($data, $packet->timestamp);
			self::$networkStackLatencyTimings->stopTiming();
		} elseif ($packet instanceof SetLocalPlayerAsInitializedPacket) {
			$data->hasAlerts = $data->player->hasPermission("ac.alerts");
			$data->loggedIn = true;
			$data->gamemode = $data->player->getGamemode();
		} elseif ($packet instanceof AdventureSettingsPacket) {
			$data->isFlying = $packet->getFlag(AdventureSettingsPacket::FLYING) || $packet->getFlag(AdventureSettingsPacket::NO_CLIP);
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
		self::$timings->stopTiming();
	}

	private function click(PlayerData $data) {
		self::$clickTimings->startTiming();
		if (count($data->clickSamples) === 20) {
			$data->clickSamples = [];
			$data->runClickChecks = false;
		}
		$data->clickSamples[] = $data->currentTick - $data->lastClickTick;
		if (count($data->clickSamples) === 20) {
			try {
				$data->cps = 20 / MathUtils::getAverage($data->clickSamples);
				if ($data->cps === 100.0) {
					// ticked once...?
					$data->isClickDataIsValid = false;
				} else {
					$data->isClickDataIsValid = true;
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
		self::$clickTimings->stopTiming();
	}

}