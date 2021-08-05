<?php

namespace ethaniccc\Esoteric\data\process;

use DivisionByZeroError;
use ErrorException;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\data\sub\protocol\InputConstants;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerBlockAction;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\EvictingList;
use ethaniccc\Esoteric\utils\LevelUtils;
use ethaniccc\Esoteric\utils\MathUtils;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Cobweb;
use pocketmine\block\Ladder;
use pocketmine\block\Liquid;
use pocketmine\block\tile\Spawnable;
use pocketmine\block\UnknownBlock;
use pocketmine\block\Vine;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PacketViolationWarningPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\GameMode;
use pocketmine\timings\TimingsHandler;
use function abs;
use function array_shift;
use function ceil;
use function count;
use function floor;
use function fmod;
use function round;

final class ProcessInbound {

	public static TimingsHandler $timings;
	public static TimingsHandler $inventoryTransactionTimings;
	public static TimingsHandler $networkStackLatencyTimings;
	public static TimingsHandler $clickTimings;
	public static TimingsHandler $movementTimings;
	public static TimingsHandler $collisionTimings;
	/** @var Block[] */
	public array $placedBlocks = [];

	private Vector3 $lastClientPrediction;
	private EvictingList $pitchRotationSamples;
	private EvictingList $yawRotationSamples;

	public function __construct() {
		self::$timings = new TimingsHandler("Esoteric Inbound Handling");
		self::$movementTimings = new TimingsHandler("Esoteric Movement Handling", self::$timings);
		self::$collisionTimings = new TimingsHandler("Esoteric Movement Collisions", self::$movementTimings);
		self::$inventoryTransactionTimings = new TimingsHandler("Esoteric Transaction Handling", self::$timings);
		self::$networkStackLatencyTimings = new TimingsHandler("Esoteric NetworkStackLatency Handling", self::$timings);
		self::$clickTimings = new TimingsHandler("Esoteric Click Handling", self::$timings);
		$this->yawRotationSamples = new EvictingList(10);
		$this->pitchRotationSamples = new EvictingList(10);
		$this->lastClientPrediction = new Vector3(0, -0.08 * 0.98, 0);
	}

	public function execute(ServerboundPacket $packet, PlayerData $data): void {
		self::$timings->startTiming();
		if ($packet instanceof PlayerAuthInputPacket && $data->loggedIn) {
			$data->packetDeltas[$packet->getTick()] = $packet->getDelta();
			if (count($data->packetDeltas) > 20) {
				array_shift($data->packetDeltas);
			}
			$location = Location::fromObject($packet->getPosition()->subtract(0, 1.62, 0), $data->player->getWorld(), $packet->getYaw(), $packet->getPitch());
			$data->inLoadedChunk = $data->chunkSendPosition->distance($data->currentLocation->floor()) <= $data->player->getViewDistance() * 16;
			$data->teleported = false;
			$data->hasMovementSuppressed = false;
			$data->lastLocation = clone $data->currentLocation;
			$data->currentLocation = $location;
			$data->currentMoveDelta = $data->currentLocation->subtractVector($data->lastLocation)->asVector3();
			$data->previousYaw = $data->currentYaw;
			$data->previousPitch = $data->currentPitch;
			$data->currentYaw = $location->yaw;
			$data->currentPitch = $location->pitch;
			$data->currentYawDelta = abs($data->currentYaw - $data->previousYaw);
			$data->currentPitchDelta = abs($data->currentPitch - $data->previousPitch);
			if ($data->currentYawDelta > 180) {
				$data->currentYawDelta = 360 - $data->currentYawDelta;
			}
			if ($data->currentYawDelta > 0) {
				$this->yawRotationSamples->add($data->currentYawDelta);
				if ($this->yawRotationSamples->full()) {
					$count = 0;
					$this->yawRotationSamples->iterate(static function (float $delta) use(&$count): void {
						$fullKeyboardSens = round(round($delta, 2) * MovementConstants::FULL_KEYBOARD_ROTATION_MULTIPLIER, 3);
						if (fmod($fullKeyboardSens, 1) <= 1E-7) {
							++$count;
						}
					});
					$passedYaw = true;
					$data->isFullKeyboardGameplay = $count > 0;
					$this->yawRotationSamples->clear();
				}
			}
			if ($data->currentPitchDelta > 0) {
				$this->pitchRotationSamples->add($data->currentPitchDelta);
				if ($this->pitchRotationSamples->full()) {
					$count = 0;
					$this->pitchRotationSamples->iterate(static function (float $delta) use(&$count): void {
						$fullKeyboardSens = round(round($delta, 2) * MovementConstants::FULL_KEYBOARD_ROTATION_MULTIPLIER, 3);
						if (fmod($fullKeyboardSens, 1) <= 1E-7) {
							++$count;
						}
					});
					$data->isFullKeyboardGameplay = isset($passedYaw) || $count > 0;
					$this->pitchRotationSamples->clear();
				}
			}
			$data->boundingBox = AABB::from($data);
			$data->directionVector = MathUtils::directionVectorFromValues($data->currentYaw, $data->currentPitch);
			$validMovement = $data->currentMoveDelta->lengthSquared() >= MovementConstants::MOVEMENT_THRESHOLD_SQUARED;

			if ($validMovement || $data->currentYawDelta > 0 || $data->currentPitchDelta > 0) {
				$pk = new MovePlayerPacket();
				$pk->entityRuntimeId = $data->player->getId();
				$pk->position = $packet->getPosition();
				$pk->yaw = $location->yaw;
				$pk->headYaw = $packet->getHeadYaw();
				$pk->pitch = $location->pitch;
				$pk->mode = MovePlayerPacket::MODE_NORMAL;
				$pk->onGround = $data->onGround;
				$pk->tick = $packet->getTick();
				$data->player->getNetworkSession()->getHandler()->handleMovePlayer($pk);
			}

			if (InputConstants::hasFlag($packet, InputConstants::START_SPRINTING)) {
				$data->jumpMovementFactor = MovementConstants::JUMP_MOVE_SPRINT;
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $data->player->getId();
				$pk->action = PlayerActionPacket::ACTION_START_SPRINT;
				$pk->x = $location->x;
				$pk->y = $location->y;
				$pk->z = $location->z;
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::STOP_SPRINTING)) {
				$data->jumpMovementFactor = MovementConstants::JUMP_MOVE_NORMAL;
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $data->player->getId();
				$pk->action = PlayerActionPacket::ACTION_STOP_SPRINT;
				$pk->x = $location->x;
				$pk->y = $location->y;
				$pk->z = $location->z;
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::START_SNEAKING)) {
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $data->player->getId();
				$pk->action = PlayerActionPacket::ACTION_START_SNEAK;
				$pk->x = $location->x;
				$pk->y = $location->y;
				$pk->z = $location->z;
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::STOP_SNEAKING)) {
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $data->player->getId();
				$pk->action = PlayerActionPacket::ACTION_STOP_SNEAK;
				$pk->x = $location->x;
				$pk->y = $location->y;
				$pk->z = $location->z;
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::START_JUMPING)) {
				$data->ticksSinceJump = 0;
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $data->player->getId();
				$pk->action = PlayerActionPacket::ACTION_JUMP;
				$pk->x = $location->x;
				$pk->y = $location->y;
				$pk->z = $location->z;
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::START_GLIDING)) {
				$data->isGliding = true;
			}
			if (InputConstants::hasFlag($packet, InputConstants::STOP_GLIDING)) {
				$data->isGliding = false;
			}

			if ($packet->blockActions !== null) {
				foreach ($packet->blockActions as $action) {
					switch ($action->actionType) {
						case PlayerBlockAction::START_BREAK:
							$pk = new PlayerActionPacket();
							$pk->entityRuntimeId = $data->player->getId();
							$pk->action = PlayerActionPacket::ACTION_START_BREAK;
							$pk->x = $action->blockPos->x;
							$pk->y = $action->blockPos->y;
							$pk->z = $action->blockPos->z;
							$pk->face = $data->player->getHorizontalFacing();
							$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
							break;
						case PlayerBlockAction::CONTINUE:
						case PlayerBlockAction::CRACK_BREAK:
							$pk = new PlayerActionPacket();
							$pk->entityRuntimeId = $data->player->getId();
							$pk->action = PlayerActionPacket::ACTION_CRACK_BREAK;
							$pk->x = $action->blockPos->x;
							$pk->y = $action->blockPos->y;
							$pk->z = $action->blockPos->z;
							$pk->face = $data->player->getHorizontalFacing();
							$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
							break;
						case PlayerBlockAction::ABORT_BREAK:
							$pk = new PlayerActionPacket();
							$pk->entityRuntimeId = $data->player->getId();
							$pk->action = PlayerActionPacket::ACTION_ABORT_BREAK;
							$pk->x = $action->blockPos->x;
							$pk->y = $action->blockPos->y;
							$pk->z = $action->blockPos->z;
							$pk->face = $data->player->getHorizontalFacing();
							$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
							break;
						case PlayerBlockAction::STOP_BREAK:
							$pk = new PlayerActionPacket();
							$pk->entityRuntimeId = $data->player->getId();
							$pk->action = PlayerActionPacket::ACTION_STOP_BREAK;
							$pk->x = $location->x;
							$pk->y = $location->y;
							$pk->z = $location->z;
							$pk->face = $data->player->getHorizontalFacing();
							$data->player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
							break;
						case PlayerBlockAction::PREDICT_DESTROY:
							break;
					}
				}
			}

			if ($packet->itemInteractionData !== null) {
				// maybe if :microjang: didn't make the block breaking server-side option redundant, I wouldn't be doing this... you know who to blame !
				// hahaha... skidding PMMP go brrrt
				$player = $data->player;
				$player->doCloseInventory();
				$item = $player->getInventory()->getItemInHand();
				$oldItem = clone $item;
				$canInteract = $player->canInteract($packet->itemInteractionData->blockPos->add(0.5, 0.5, 0.5), $player->isCreative() ? 13 : 7);
				$useBreakOn = $player->getWorld()->useBreakOn($packet->itemInteractionData->blockPos, $item, $player, true);
				if ($canInteract and $useBreakOn && $player->isSurvival()) {
					if (!$item->equalsExact($oldItem) and $oldItem->equalsExact($player->getInventory()->getItemInHand())) {
						$player->getInventory()->setItemInHand($item);
					}
				}
				$target = $player->getWorld()->getBlock($packet->itemInteractionData->blockPos);
				// $blocks = $target->getAllSides();
				// $blocks[] = $target;
				$pk = new UpdateBlockPacket();
				$p = $target->getPos();
				$pk->x = $p->x;
				$pk->y = $p->y;
				$pk->z = $p->z;
				$pk->dataLayerId = UpdateBlockPacket::DATA_LAYER_NORMAL;
				if($target instanceof Liquid){
					$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId((BlockLegacyIds::AIR << 4) | 0);
				} else {
					$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId($target->getFullId());
				}
				$data->player->getNetworkSession()->addToSendBuffer($pk);
				/*foreach ($blocks as $b) {
					$tile = $player->getWorld()->getTile($b->getPos());
					if ($tile instanceof Spawnable) {
						$tile->spawnTo($player);
					}
					$p = $b->getPos();
					$pk->x = $p->x;
					$pk->y = $p->y;
					$pk->z = $p->z;
					$pk->dataLayerId = UpdateBlockPacket::DATA_LAYER_NORMAL;
					if($b instanceof Liquid){
						$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId((BlockLegacyIds::AIR << 4) | 0);
					} else {
						$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId($b->getFullId());
					}
					$data->player->getNetworkSession()->addToSendBuffer($pk);

				}*/
			}

			$data->jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;
			$data->canPlaceBlocks = $data->gamemode->equals(GameMode::SURVIVAL()) || $data->gamemode->equals(GameMode::CREATIVE());

			foreach ($data->effects as $effectData) {
				$effectData->ticks--;
				if ($effectData->ticks <= 0) {
					unset($data->effects[$effectData->effectId]);
				} else {
					switch ($effectData->effectId) {
						case VanillaEffects::JUMP_BOOST()->getRuntimeId():
							$data->jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION + ($effectData->amplifier / 10);
							break;
						case VanillaEffects::LEVITATION()->getRuntimeId():
							$data->ticksSinceFlight = 0;
							break;
					}
				}
			}

			if ($validMovement) {
				self::$collisionTimings->startTiming();
				// LevelUtils::checkBlocksInAABB() is basically a duplicate of getCollisionBlocks, but in here, it will get all blocks
				// if the block doesn't have an AABB, this assumes a 1x1x1 AABB for that block
				$blocks = LevelUtils::checkBlocksInAABB($data->boundingBox->expandedCopy(0.5, 0.5, 0.5), $location->getWorld(), LevelUtils::SEARCH_ALL, false);
				$data->expectedOnGround = false;
				$data->isCollidedHorizontally = false;
				$data->isCollidedVertically = false;
				$liquids = 0;
				$climbable = 0;
				$cobweb = 0;
				// TODO: Improve vertical and horizontal collisions to work with checks (pReDicTioN bRo)
				$horizontalAABB = $data->boundingBox->expandedCopy(0.25, 0, 0.25);
				$verticalAABB = $data->boundingBox->expandedCopy(0.25, MovementConstants::GROUND_MODULO * 2, 0.25);
				$hasAbove = false;
				/** @var Block $block */
				foreach ($blocks as $block) {
					if (!$data->isCollidedHorizontally) {
						// snow layers are evil
						$data->isCollidedHorizontally = $block->getId() !== BlockLegacyIds::AIR && (count($block->getCollisionBoxes()) === 0 ? AABB::fromBlock($block)->intersectsWith($horizontalAABB) : $block->collidesWithBB($horizontalAABB));
					}
					if ($block->getId() !== BlockLegacyIds::AIR && (count($block->getCollisionBoxes()) === 0 ? AABB::fromBlock($block)->intersectsWith($verticalAABB) : $block->collidesWithBB($verticalAABB))) {
						$data->isCollidedVertically = true;
						if (floor($block->getPos()->y) <= floor($location->y)) {
							$data->expectedOnGround = true;
						} else {
							$hasAbove = true;
						}
					}
					if ($block instanceof Liquid) {
						$liquids++;
					} elseif ($block instanceof Cobweb) {
						$cobweb++;
					} elseif ($block instanceof Ladder || $block instanceof Vine) {
						$climbable++;
					}
				}
				if ($liquids > 0) $data->ticksSinceInLiquid = 0; else ++$data->ticksSinceInLiquid;
				if ($cobweb > 0) $data->ticksSinceInCobweb = 0; else ++$data->ticksSinceInCobweb;
				if ($climbable > 0) $data->ticksSinceInClimbable = 0; else ++$data->ticksSinceInClimbable;
				self::$collisionTimings->stopTiming();
				$actualMoveY = $data->currentMoveDelta->y;
				$predictedMoveY = $this->lastClientPrediction->y;
				if($data->ticksSinceMotion === 0) $predictedMoveY = $data->motion->y;
				if($data->ticksSinceJump === 0) $predictedMoveY = $data->jumpVelocity;
				$flag3 = abs($predictedMoveY - $actualMoveY) > 0.001;
				$flag4 = $predictedMoveY < 0 || $data->isCollidedHorizontally;
				$data->hasBlockAbove = $flag3 && $predictedMoveY > 0 && abs($predictedMoveY) > 0.005 && $hasAbove;
				$data->onGround = $flag3 && $flag4 && $data->expectedOnGround;

				if($data->onGround && $data->isGliding) $data->isGliding = false;
			}

			if (!$data->inLoadedChunk) {
				$data->expectedOnGround = true;
				$data->onGround = true;
			}

			/**
			 * Checks if there is a possible ghost block that the player is standing on. If there is a ghost block that the player is standing on,
			 * we should remove it to prevent possible false-flags with a GroundSpoof check.
			 */

			// TODO: There's a stupid bug where setting a block with UpdateBlockPacket won't do anything, make future attempts to fix this BS.

			foreach ($this->placedBlocks as $blockVector) {
				$blockPos = $blockVector->getPos();
				$hasCollision = count($blockVector->getCollisionBoxes()) === 0 ? AABB::fromBlock($blockVector)->intersectsWith($data->boundingBox->expandedCopy(0.1, 0.1, 0.1)) : $blockVector->collidesWithBB($data->boundingBox->expandedCopy(0.1, 0.1, 0.1));
				if ($hasCollision) {
					$data->expectedOnGround = true;
					$data->onGround = true;
					$data->isCollidedHorizontally = $blockPos->y >= floor($location->y) && $blockPos->y - $location->y <= ceil($data->hitboxHeight);
					$data->isCollidedVertically = true;
				}
				if ($validMovement || $hasCollision) {
					$realBlock = $data->player->getWorld()->getBlock($blockPos, false, false);
					$handler = NetworkStackLatencyHandler::getInstance();
					$data->networkStackLatencyHandler->queue($data, function () use ($hasCollision, &$data, $realBlock, $handler, $blockPos): void {
						$pk = new UpdateBlockPacket();
						$pk->x = $blockPos->x;
						$pk->y = $blockPos->y;
						$pk->z = $blockPos->z;
						$pk->dataLayerId = UpdateBlockPacket::DATA_LAYER_NORMAL;
						if ($realBlock instanceof Liquid) {
							$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId((BlockLegacyIds::AIR << 4) | 0);
							$data->player->getNetworkSession()->addToSendBuffer($pk);
						}
						$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId($realBlock->getFullId());
						$data->player->getNetworkSession()->addToSendBuffer($pk);
						if ($hasCollision && floor($data->currentLocation->y) > $blockPos->y) {
							// prevent the player from possibly false flagging when removing ghost blocks fail
							$data->player->teleport(new Vector3($data->currentLocation->x, $blockPos->y, $data->currentLocation->z));
						}
						$handler->queue($data, function () use (&$data, $realBlock): void {
							foreach ($this->placedBlocks as $key => $vector) {
								if ($vector->getPos()->equals($realBlock->getPos())) {
									unset($this->placedBlocks[$key]);
									break;
								}
							}
						});
					});
				}
			}

			if ($data->onGround) {
				$data->offGroundTicks = 0;
				$data->lastOnGroundLocation = clone $data->currentLocation;
			} else {
				++$data->offGroundTicks;
			}
			++$data->ticksSinceMotion;
			if ($data->ticksSinceTeleport <= 1) {
				$data->teleported = true;
				if ($data->ticksSinceTeleport === 0) {
					$data->currentMoveDelta = clone MathUtils::$ZERO_VECTOR;
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
			if ($data->isAlive) {
				++$data->ticksSinceSpawn;
			} else {
				$data->ticksSinceSpawn = 0;
			}
			if ($data->isGliding) {
				$data->ticksSinceGlide = 0;
			} else {
				++$data->ticksSinceGlide;
			}

			$data->moveForward = $packet->getMoveVecZ() * 0.98;
			$data->moveStrafe = $packet->getMoveVecX() * 0.98;

			$data->isInVoid = $location->y <= -35;

			$this->lastClientPrediction = $packet->getDelta();
			$data->tick();
		} elseif ($packet instanceof InventoryTransactionPacket) {
			self::$inventoryTransactionTimings->startTiming();
			$trData = $packet->trData;
			if ($trData instanceof UseItemOnEntityTransactionData) {
				$data->target = $trData->getEntityRuntimeId();
				$data->attackPos = $trData->getPlayerPos();
				$this->click($data);
			} elseif ($trData instanceof UseItemTransactionData) {
				if ($trData->getActionType() === UseItemTransactionData::ACTION_CLICK_BLOCK) {
					$newBlockPos = $trData->getBlockPos()->getSide($trData->getFace());
					$blockToReplace = $data->player->getWorld()->getBlock($newBlockPos, false, false);
					if ($blockToReplace->canBeReplaced() && $data->canPlaceBlocks) {
						$stack = $trData->getItemInHand()->getItemStack();
						if ($stack->getBlockRuntimeId() === 0) {
							return; // the item in hand is NOT a block
						}
						$state = RuntimeBlockMapping::getInstance()->fromRuntimeId($stack->getBlockRuntimeId());
						$block = BlockFactory::getInstance()->get($state >> 4, $state & 0xf);
						if ($stack->getId() < 0) {
							$block = new UnknownBlock(new BlockIdentifier($stack->getId(), $stack->getMeta()), LevelUtils::$ZERO_BREAK_INFO);
						}
						if ($blockToReplace->canBeReplaced() && ($block instanceof UnknownBlock || $block->canBePlaced()) && $data->canPlaceBlocks && $data->boundingBox !== null && !$block->collidesWithBB($data->boundingBox)) {
							if ($trData->getItemInHand()->getItemStack()->getId() < 0) {
								$stack = $trData->getItemInHand()->getItemStack();
								$block = new UnknownBlock(new BlockIdentifier($stack->getId(), $stack->getMeta()), LevelUtils::$ZERO_BREAK_INFO);
								$p = $blockToReplace->getPos();
								$block->position($p->getWorld(), $p->x, $p->y, $p->z);
								if ($block->collidesWithBB($data->boundingBox)) {
									return;
								}
							}
							foreach ($this->placedBlocks as $other) {
								if ($other->getPos()->equals($blockToReplace->getPos())) {
									return;
								}
							}
							if (($block->canBePlaced() || $block instanceof UnknownBlock)) {
								$p = $blockToReplace->getPos();
								$block->position($p->getWorld(), $p->x, $p->y, $p->z);
								$blockAABB = AABB::fromBlock($block);
								if ((!$block instanceof UnknownBlock || $block->isSolid() && !$block->isTransparent()) /* <- so let's talk about that.... */ && $blockAABB->intersectsWith($data->boundingBox->expandedCopy(0.01, 0.01, 0.01))) {
									return;
								}
								$this->placedBlocks[] = clone $block;
							}
						}
					}
				}
			}
			self::$inventoryTransactionTimings->stopTiming();
		} elseif ($packet instanceof NetworkStackLatencyPacket) {
			self::$networkStackLatencyTimings->startTiming();
			NetworkStackLatencyHandler::getInstance()->execute($data, $packet->timestamp);
			self::$networkStackLatencyTimings->stopTiming();
		} elseif ($packet instanceof SetLocalPlayerAsInitializedPacket) {
			$data->loggedIn = true;
			$data->gamemode = $data->player->getGamemode();
		} elseif ($packet instanceof AdventureSettingsPacket) {
			$data->isFlying = $packet->getFlag(AdventureSettingsPacket::FLYING) || $packet->getFlag(AdventureSettingsPacket::NO_CLIP);
		} elseif ($packet instanceof LoginPacket) {
			$clientData = PacketUtils::parseClientData($packet->clientDataJwt);
			$data->playerOS = $clientData->DeviceOS;
		} elseif ($packet instanceof LevelSoundEventPacket) {
			if ($packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE) {
				$this->click($data);
			}
		} elseif ($packet instanceof PacketViolationWarningPacket) {
			Esoteric::getInstance()->loggerThread->write("Violation warning for {$data->player->getName()} || (message={$packet->getMessage()} sev={$packet->getSeverity()} pkID={$packet->getPacketId()})");
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
				$data->cps = 20 / MathUtils::getAverage(...$data->clickSamples);
			} catch (ErrorException|DivisionByZeroError) {
				$data->cps = INF;
			}

			$data->kurtosis = MathUtils::getKurtosis(...$data->clickSamples);
			$data->skewness = MathUtils::getSkewness(...$data->clickSamples);
			$data->deviation = MathUtils::getDeviation(...$data->clickSamples);
			$data->outliers = MathUtils::getOutliers(...$data->clickSamples);
			$data->variance = MathUtils::getVariance(...$data->clickSamples);
			$data->runClickChecks = true;
		}
		$data->lastClickTick = $data->currentTick;
		self::$clickTimings->stopTiming();
	}

}
