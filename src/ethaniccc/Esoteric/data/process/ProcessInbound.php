<?php

namespace ethaniccc\Esoteric\data\process;

use ErrorException;
use ethaniccc\Esoteric\Constants;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\protocol\InputConstants;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\EvictingList;
use ethaniccc\Esoteric\utils\LevelUtils;
use ethaniccc\Esoteric\utils\MathUtils;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\Cobweb;
use pocketmine\block\Ladder;
use pocketmine\block\Liquid;
use pocketmine\block\UnknownBlock;
use pocketmine\block\Vine;
use pocketmine\data\bedrock\EffectIds;
use pocketmine\data\java\GameModeIdMap;
use pocketmine\entity\Attribute;
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
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionStopBreak;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\timings\TimingsHandler;
use function abs;
use function array_shift;
use function count;
use function floor;
use function fmod;
use function in_array;
use function round;

final class ProcessInbound{

	/** @var TimingsHandler */
	public static ?TimingsHandler $timings = null;
	/** @var TimingsHandler */
	public static TimingsHandler $inventoryTransactionTimings;
	/** @var TimingsHandler */
	public static TimingsHandler $networkStackLatencyTimings;
	/** @var TimingsHandler */
	public static TimingsHandler $clickTimings;
	/** @var TimingsHandler */
	public static TimingsHandler $movementTimings;
	/** @var TimingsHandler */
	public static TimingsHandler $collisionTimings;

	/** @var Block[] */
	public array $queuedBlocks = [];
	/** @var Block[] */
	public array $needUpdateBlocks = [];

	private float $lastPredictedY = 0.0;
	private EvictingList $pitchRotationSamples;
	private EvictingList $yawRotationSamples;

	public function __construct(){
		if(self::$timings === null){
			self::$timings = new TimingsHandler("Esoteric Inbound Handling");
			self::$movementTimings = new TimingsHandler("Esoteric Movement Handling", self::$timings);
			self::$collisionTimings = new TimingsHandler("Esoteric Movement Collisions", self::$movementTimings);
			self::$inventoryTransactionTimings = new TimingsHandler("Esoteric Transaction Handling", self::$timings);
			self::$networkStackLatencyTimings = new TimingsHandler("Esoteric NetworkStackLatency Handling", self::$timings);
			self::$clickTimings = new TimingsHandler("Esoteric Click Handling", self::$timings);
		}
		$this->yawRotationSamples = new EvictingList(10);
		$this->pitchRotationSamples = new EvictingList(10);
	}

	public function execute(ServerboundPacket $packet, PlayerData $data) : void{
		self::$timings->startTiming();
		if($packet instanceof PlayerAuthInputPacket && $data->loggedIn){
			$data->blockBroken = null;
			$data->packetDeltas[$packet->getTick()] = $packet->getDelta();
			if(count($data->packetDeltas) > 20){
				array_shift($data->packetDeltas);
			}
			$location = Location::fromObject($packet->getPosition()->subtract(0, 1.62, 0), $data->player->getWorld(), $packet->getYaw(), $packet->getPitch());
			$data->inLoadedChunk = $data->world->getChunk(floor($location->x) >> 4, floor($location->z) >> 4) !== null;
			$data->teleported = false;
			$data->hasMovementSuppressed = false;
			$data->lastLocation = clone $data->currentLocation;
			$data->currentLocation = $location;
			$data->lastMoveDelta = $data->currentMoveDelta;
			$data->currentMoveDelta = $data->currentLocation->subtractVector($data->lastLocation)->asVector3();
			$data->previousYaw = $data->currentYaw;
			$data->previousPitch = $data->currentPitch;
			$data->currentYaw = $location->yaw;
			$data->currentPitch = $location->pitch;
			$data->lastYawDelta = $data->currentYawDelta;
			$data->lastPitchDelta = $data->currentPitchDelta;
			$data->currentYawDelta = abs($data->currentYaw - $data->previousYaw);
			$data->currentPitchDelta = abs($data->currentPitch - $data->previousPitch);
			if($data->currentYawDelta > 180){
				$data->currentYawDelta = 360 - $data->currentYawDelta;
			}
			if($data->currentYawDelta > 0){
				$this->yawRotationSamples->add($data->currentYawDelta);
				if($this->yawRotationSamples->full()){
					$count = 0;
					$this->yawRotationSamples->iterate(static function(float $delta) use (&$count) : void{
						$fullKeyboardSens = round(round($delta, 2) * MovementConstants::FULL_KEYBOARD_ROTATION_MULTIPLIER, 3);
						if(fmod($fullKeyboardSens, 1) <= 1E-7){
							++$count;
						}
					});
					$passedYaw = true;
					$data->isFullKeyboardGameplay = $count > 0;
					$this->yawRotationSamples->clear();
				}
			}
			if($data->currentPitchDelta > 0){
				$this->pitchRotationSamples->add($data->currentPitchDelta);
				if($this->pitchRotationSamples->full()){
					$count = 0;
					$this->pitchRotationSamples->iterate(static function(float $delta) use (&$count) : void{
						$fullKeyboardSens = round(round($delta, 2) * MovementConstants::FULL_KEYBOARD_ROTATION_MULTIPLIER, 3);
						if(fmod($fullKeyboardSens, 1) <= 1E-7){
							++$count;
						}
					});
					$data->isFullKeyboardGameplay = isset($passedYaw) || $count > 0;
					$this->pitchRotationSamples->clear();
				}
			}
			//$data->player->sendMessage("fullKeyboardGameplay=" . var_export($data->isFullKeyboardGameplay, true));
			$data->boundingBox = AABB::from($data);
			$data->directionVector = MathUtils::directionVectorFromValues($data->currentYaw, $data->currentPitch);
			$validMovement = $data->currentMoveDelta->lengthSquared() >= MovementConstants::MOVEMENT_THRESHOLD_SQUARED;
			$data->movementSpeed = $data->player->getAttributeMap()->get(Attribute::MOVEMENT_SPEED)?->getValue();

			if($validMovement || $data->currentYawDelta > 0 || $data->currentPitchDelta > 0){
				$pk = new MovePlayerPacket();
				$pk->actorRuntimeId = $data->player->getId();
				$pk->position = $packet->getPosition();
				$pk->yaw = $location->yaw;
				$pk->headYaw = $packet->getHeadYaw();
				$pk->pitch = $location->pitch;
				$pk->mode = MovePlayerPacket::MODE_NORMAL;
				$pk->onGround = $data->onGround;
				$pk->tick = $packet->getTick();
				$data->player->getNetworkSession()->getHandler()?->handleMovePlayer($pk);
			}

			if(InputConstants::hasFlag($packet, InputConstants::START_SPRINTING)){
				$data->isSprinting = true;
				$data->jumpMovementFactor = MovementConstants::JUMP_MOVE_SPRINT;
				$pk = new PlayerActionPacket();
				$pk->actorRuntimeId = $data->player->getId();
				$pk->action = PlayerAction::START_SPRINT;
				$pk->blockPosition = BlockPosition::fromVector3($location);
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
			}
			if(InputConstants::hasFlag($packet, InputConstants::STOP_SPRINTING)){
				$data->isSprinting = false;
				$data->jumpMovementFactor = MovementConstants::JUMP_MOVE_NORMAL;
				$pk = new PlayerActionPacket();
				$pk->actorRuntimeId = $data->player->getId();
				$pk->action = PlayerAction::STOP_SPRINT;
				$pk->blockPosition = BlockPosition::fromVector3($location);
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
			}
			if(InputConstants::hasFlag($packet, InputConstants::START_SNEAKING)){
				$pk = new PlayerActionPacket();
				$pk->actorRuntimeId = $data->player->getId();
				$pk->action = PlayerAction::START_SNEAK;
				$pk->blockPosition = BlockPosition::fromVector3($location);
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
			}
			if(InputConstants::hasFlag($packet, InputConstants::STOP_SNEAKING)){
				$pk = new PlayerActionPacket();
				$pk->actorRuntimeId = $data->player->getId();
				$pk->action = PlayerAction::STOP_SNEAK;
				$pk->blockPosition = BlockPosition::fromVector3($location);
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
			}
			if(InputConstants::hasFlag($packet, InputConstants::START_JUMPING)){
				$data->ticksSinceJump = 0;
				$pk = new PlayerActionPacket();
				$pk->actorRuntimeId = $data->player->getId();
				$pk->action = PlayerAction::JUMP;
				$pk->blockPosition = BlockPosition::fromVector3($location);
				$pk->face = $data->player->getHorizontalFacing();
				$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
			}
			if(InputConstants::hasFlag($packet, InputConstants::START_GLIDING)){
				$data->isGliding = true;
			}
			if(InputConstants::hasFlag($packet, InputConstants::STOP_GLIDING)){
				$data->isGliding = false;
			}

			if(InputConstants::hasFlag($packet, InputConstants::PERFORM_BLOCK_ACTIONS)){
				if($packet->getBlockActions() !== null){
					foreach($packet->getBlockActions() as $action){
						if($action instanceof PlayerBlockActionStopBreak){
							// hmm
						}elseif($action instanceof PlayerBlockActionWithBlockInfo){
							switch($action->getActionType()){
								case PlayerAction::START_BREAK:
									$pk = new PlayerActionPacket();
									$pk->actorRuntimeId = $data->player->getId();
									$pk->action = PlayerAction::START_BREAK;
									$pk->blockPosition = clone $action->getBlockPosition();
									$pk->face = $data->player->getHorizontalFacing();
									$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
									break;
								case PlayerAction::CONTINUE_DESTROY_BLOCK:
								case PlayerAction::CRACK_BREAK:
									$pk = new PlayerActionPacket();
									$pk->actorRuntimeId = $data->player->getId();
									$pk->action = PlayerAction::CRACK_BREAK;
									$pk->blockPosition = clone $action->getBlockPosition();
									$pk->face = $data->player->getHorizontalFacing();
									$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
									break;
								case PlayerAction::ABORT_BREAK:
									$pk = new PlayerActionPacket();
									$pk->actorRuntimeId = $data->player->getId();
									$pk->action = PlayerAction::ABORT_BREAK;
									$pk->blockPosition = clone $action->getBlockPosition();
									$pk->face = $data->player->getHorizontalFacing();
									$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
									break;
								case PlayerAction::STOP_BREAK:
									$pk = new PlayerActionPacket();
									$pk->actorRuntimeId = $data->player->getId();
									$pk->action = PlayerAction::STOP_BREAK;
									$pk->blockPosition = BlockPosition::fromVector3($location);
									$pk->face = $data->player->getHorizontalFacing();
									$data->player->getNetworkSession()->getHandler()?->handlePlayerAction($pk);
									break;
							}
						}
					}
				}
			}


			if($packet->hasFlag(PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION)){
				// maybe if :microjang: didn't make the block breaking server-side option redundant, I wouldn't be doing this... you know who to blame !
				// hahaha... skidding PMMP go brrrt
				$player = $data->player;
				$player->removeCurrentWindow();
				$item = $player->getInventory()->getItemInHand();
				$oldItem = clone $item;
				$pos = new Vector3(($bPos = $packet->itemInteractionData->getTransactionData()->getBlockPosition())->getX(), $bPos->getY(), $bPos->getZ());
				$currentBlock = $data->player->getWorld()->getBlock($pos);
				$canInteract = $player->canInteract($pos->add(0.5, 0.5, 0.5), $player->isCreative() ? 13 : 7);
				$useBreakOn = $player->getWorld()->useBreakOn($pos, $item, $player, true);
				if($canInteract and $useBreakOn){
					if($player->isSurvival() && !$item->equalsExact($oldItem) and $oldItem->equalsExact($player->getInventory()->getItemInHand())){
						$player->getInventory()->setItemInHand($item);
					}
					// can you even break more than 1 block in a tick?
					$data->blockBroken = clone $currentBlock;
				}
			}

			$data->jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;

			$data->canPlaceBlocks = $data->gamemode === GameMode::SURVIVAL || $data->gamemode === GameMode::CREATIVE;

			foreach($data->effects as $effectData){
				$effectData->ticks--;
				if($effectData->ticks <= 0){
					unset($data->effects[$effectData->effectId]);
				}else{
					switch($effectData->effectId){
						case EffectIds::JUMP_BOOST:
							$data->jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION + ($effectData->amplifier / 10);
							break;
						case EffectIds::LEVITATION:
							$data->ticksSinceFlight = 0;
							break;
					}
				}
			}

			if($validMovement){
				self::$collisionTimings->startTiming();
				// LevelUtils::checkBlocksInAABB() is basically a duplicate of getCollisionBlocks, but in here, it will get all blocks
				// if the block doesn't have an AABB, this assumes a 1x1x1 AABB for that block
				$blocks = LevelUtils::checkBlocksInAABB($data->boundingBox->toAABB()->expandedCopy(1, 1, 1), $data->world, LevelUtils::SEARCH_ALL, false);
				$data->expectedOnGround = false;
				$data->lastBlocksBelow = $data->blocksBelow;
				$data->blocksBelow = [];
				$data->isCollidedHorizontally = false;
				$data->isCollidedVertically = false;
				$liquids = 0;
				$climbable = 0;
				$cobweb = 0;
				if($data->currentLocation->y > 0 && $data->currentLocation->y < 255){
					$verticalBB = $data->boundingBox->toAABB()->expandedCopy(0.25, 0.01, 0.25);
					$horizontalBB = $data->boundingBox->toAABB()->expandedCopy(0.5, -0.01, 0.5);
					foreach($blocks as $block){
						/** @var Block $block */
						if(!$data->isCollidedHorizontally){
							$data->isCollidedHorizontally = $block->collidesWithBB($horizontalBB);
						}
						if(floor($block->getPosition()->y) <= floor($location->y) && $block->collidesWithBB($verticalBB)){
							$data->expectedOnGround = true;
							$data->blocksBelow[] = $block;
							$data->isCollidedVertically = true;
						}elseif(floor($block->getPosition()->y) > floor($location->y) && $block->collidesWithBB($verticalBB)){
							$data->isCollidedVertically = true;
						}
						if($block instanceof Liquid){
							$liquids++;
						}elseif($block instanceof Cobweb){
							$cobweb++;
						}elseif($block instanceof Ladder || $block instanceof Vine){
							$climbable++;
						}
					}
					if($liquids > 0){
						$data->ticksSinceInLiquid = 0;
					}else{
						++$data->ticksSinceInLiquid;
					}

					if($cobweb > 0){
						$data->ticksSinceInCobweb = 0;
					}else{
						++$data->ticksSinceInCobweb;
					}

					if($climbable > 0){
						$data->ticksSinceInClimbable = 0;
					}else{
						++$data->ticksSinceInClimbable;
					}
					self::$collisionTimings->stopTiming();
					$expectedMoveY = ($data->lastMoveDelta->y - MovementConstants::Y_SUBTRACTION) * MovementConstants::Y_MULTIPLICATION;
					$actualMoveY = $data->currentMoveDelta->y;
					$flag1 = abs($expectedMoveY - $actualMoveY) > 0.001;
					$flag2 = $expectedMoveY < 0;
					$predictedMoveY = $this->lastPredictedY;
					if($data->ticksSinceMotion === 0){
						$predictedMoveY = $data->motion->y;
					}
					if($data->ticksSinceJump === 0){
						$predictedMoveY = $data->jumpVelocity;
					}
					$data->hasBlockAbove = $flag1 && $expectedMoveY > 0 && abs($expectedMoveY) > 0.005 && $data->isCollidedVertically;
					$flag3 = abs($predictedMoveY - $actualMoveY) > 0.001;
					$flag4 = $predictedMoveY < 0 || $data->isCollidedHorizontally;
					$data->onGround = $flag3 && $flag4 && $data->expectedOnGround;

					if($data->ticksSinceTeleport <= 1){
						$data->onGround = true;
					}

					if($data->onGround && $data->isGliding){
						/**
						 * This happens because sometimes the bloody client decides to not give a STOP_GLIDE flag in PlayerAuthInputPacket.
						 * FFS microjang, fix your broken game....
						 */
						$data->isGliding = false;
					}
				}
			}

			/**
			 * Checks if there is a possible ghost block that the player is standing on. If there is a ghost block that the player is standing on,
			 * we should remove it to prevent possible false-flags with a GroundSpoof check.
			 */

			// TODO: There's a stupid bug where setting a block with UpdateBlockPacket won't do anything, make future attempts to fix this BS.

			foreach($this->needUpdateBlocks as $blockVector){
				$blockPos = $blockVector->getPosition();
				$hasCollision = $blockVector->collidesWithBB($data->boundingBox->toAABB()->expandedCopy(0.2, 0.2, 0.2));
				if($hasCollision){
					$data->expectedOnGround = true;
					$data->onGround = true;
					$data->isCollidedHorizontally = $blockPos->y > floor($location->y);
					if($blockPos->y <= floor($location->y)){
						$data->blocksBelow[] = $blockVector;
					}
				}
				if($validMovement || $hasCollision){
					$realBlock = $data->player->getWorld()->getBlock($blockPos, false, false);
					$handler = NetworkStackLatencyHandler::getInstance();
					$handler->queue($data, function(int $timestamp) use ($blockPos, $hasCollision, $data, $realBlock, $handler) : void{
						if($realBlock instanceof Liquid){
							$pk = new UpdateBlockPacket();
							$pk->blockPosition = BlockPosition::fromVector3($blockPos);
							$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId((BlockLegacyIds::AIR << 4) | 0);
							$pk->dataLayerId = UpdateBlockPacket::DATA_LAYER_NORMAL;
							$data->player->getNetworkSession()->addToSendBuffer($pk);
						}
						$pk = new UpdateBlockPacket();
						$pk->blockPosition = BlockPosition::fromVector3($blockPos);
						$pk->blockRuntimeId = RuntimeBlockMapping::getInstance()->toRuntimeId($realBlock->getFullId());
						$pk->dataLayerId = UpdateBlockPacket::DATA_LAYER_NORMAL;
						$data->player->getNetworkSession()->addToSendBuffer($pk);
						if($hasCollision && floor($data->currentLocation->y) > $blockPos->y){
							// prevent the player from possibly false flagging when removing ghost blocks fail
							$data->player->teleport(new Vector3($data->currentLocation->x, $blockPos->y, $data->currentLocation->z));
						}
						$handler->queue($data, function(int $timestamp) use ($data, $realBlock) : void{
							$data->world->setBlock($realBlock->getPosition()->asVector3(), $realBlock->getFullId());
							foreach($this->needUpdateBlocks as $key => $vector){
								if($vector->getPosition()->equals($realBlock->getPosition()->asVector3())){
									unset($this->needUpdateBlocks[$key]);
									break;
								}
							}
						});
					});
				}
			}

			if($data->onGround){
				++$data->onGroundTicks;
				$data->offGroundTicks = 0;
				$data->lastOnGroundLocation = clone $data->currentLocation;
			}else{
				++$data->offGroundTicks;
				$data->onGroundTicks = 0;
			}
			++$data->ticksSinceMotion;
			if($data->ticksSinceTeleport <= 1){
				$data->teleported = true;
				if($data->ticksSinceTeleport === 0){
					$data->currentMoveDelta = clone PlayerData::$ZERO_VECTOR;
				}
			}else{
				$data->teleported = false;
			}
			++$data->ticksSinceTeleport;
			if($data->isFlying){
				$data->ticksSinceFlight = 0;
			}else{
				++$data->ticksSinceFlight;
			}
			++$data->ticksSinceJump;
			if($data->isAlive){
				++$data->ticksSinceSpawn;
			}else{
				$data->ticksSinceSpawn = 0;
			}
			if($data->isGliding){
				$data->ticksSinceGlide = 0;
			}else{
				++$data->ticksSinceGlide;
			}

			$data->moveForward = $packet->getMoveVecZ() * 0.98;
			$data->moveStrafe = $packet->getMoveVecX() * 0.98;

			$data->isInVoid = $location->y <= -35;

			$this->lastPredictedY = $packet->getDelta()->y;
			$data->tick();
		}elseif($packet instanceof InventoryTransactionPacket){
			self::$inventoryTransactionTimings->startTiming();
			$trData = $packet->trData;
			if($trData instanceof UseItemOnEntityTransactionData){
				$data->lastTarget = $data->target;
				$data->target = $trData->getActorRuntimeId();
				$data->attackTick = $data->currentTick;
				$data->attackPos = $trData->getPlayerPosition();
				$this->click($data);
			}elseif($trData instanceof UseItemTransactionData){
				$clickedBlockPos = new Vector3($trData->getBlockPosition()->getX(), $trData->getBlockPosition()->getY(), $trData->getBlockPosition()->getZ());
				$newBlockPos = $clickedBlockPos->getSide($trData->getFace());
				$blockToReplace = $data->player->getWorld()->getBlock($newBlockPos, false, false);
				if($blockToReplace->canBeReplaced() && $data->canPlaceBlocks){
					$stack = $trData->getItemInHand()->getItemStack();
					if($stack->getBlockRuntimeId() === 0){
						return; // the item in hand is NOT a block
					}
					$state = RuntimeBlockMapping::getInstance()->fromRuntimeId($stack->getBlockRuntimeId());
					$block = BlockFactory::getInstance()->get($state >> 4, $state & 0xf);
					if($stack->getId() < 0){
						$block = new UnknownBlock(new BlockIdentifier($stack->getId(), $stack->getMeta()), new BlockBreakInfo(0));
					}
					foreach($this->queuedBlocks as $other){
						if($other->getPosition()->asVector3()->equals($blockToReplace->getPosition())){
							return;
						}
					}
					if(($block->canBePlaced() || $block instanceof UnknownBlock)){
						$block->position($blockToReplace->getPosition()->getWorld(), $newBlockPos->x, $newBlockPos->y, $newBlockPos->z);
						$data->world->setBlock($newBlockPos, $block->getFullId());
						$blockAABB = AABB::fromBlock($block);
						if((!$block instanceof UnknownBlock || ($block->isSolid() && !$block->isTransparent())) /* <- so let's talk about that.... */ && $blockAABB->toAABB()->intersectsWith($data->boundingBox->expandedCopy(0.01, 0.01, 0.01))){
							return;
						}
						$this->queuedBlocks[] = clone $block;
					}
				}
			}
			self::$inventoryTransactionTimings->stopTiming();
		}elseif($packet instanceof NetworkStackLatencyPacket){
			self::$networkStackLatencyTimings->startTiming();
			NetworkStackLatencyHandler::getInstance()->execute($data, $packet->timestamp);
			self::$networkStackLatencyTimings->stopTiming();
		}elseif($packet instanceof SetLocalPlayerAsInitializedPacket){
			$data->loggedIn = true;
			$data->gamemode = GameModeIdMap::getInstance()->toId($data->player->getGamemode());
			$data->hasAlerts = $data->player->hasPermission(Constants::ALERT_PERMISSION);
		}elseif($packet instanceof AdventureSettingsPacket){
			$data->isFlying = $packet->getFlag(AdventureSettingsPacket::FLYING) || $packet->getFlag(AdventureSettingsPacket::NO_CLIP);
		}elseif($packet instanceof LoginPacket){
			$clientData = PacketUtils::parseClientData($packet->clientDataJwt);
			$data->playerOS = $clientData->DeviceOS;
			$data->isMobile = in_array($clientData->DeviceOS, [DeviceOS::AMAZON, DeviceOS::ANDROID, DeviceOS::IOS], true);
		}elseif($packet instanceof LevelSoundEventPacket){
			if($packet->sound === LevelSoundEvent::ATTACK_NODAMAGE){
				$this->click($data);
			}
		}elseif($packet instanceof PacketViolationWarningPacket){
			Esoteric::getInstance()->logger->write("Violation warning for {$data->player->getName()} || (message={$packet->getMessage()} sev={$packet->getSeverity()} pkID={$packet->getPacketId()})");
		}
		self::$timings->stopTiming();
	}

	private function click(PlayerData $data) : void{
		self::$clickTimings->startTiming();
		if(count($data->clickSamples) === 20){
			$data->clickSamples = [];
			$data->runClickChecks = false;
		}
		$data->clickSamples[] = $data->currentTick - $data->lastClickTick;
		if(count($data->clickSamples) === 20){
			try{
				$data->cps = 20 / MathUtils::getAverage(...$data->clickSamples);
			}catch(ErrorException $e){
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
