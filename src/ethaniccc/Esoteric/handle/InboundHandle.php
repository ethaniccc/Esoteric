<?php

namespace ethaniccc\Esoteric\handle;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\listener\NetworkStackLatencyHandler;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\block\Cobweb;
use pocketmine\block\Ladder;
use pocketmine\block\Liquid;
use pocketmine\block\UnknownBlock;
use pocketmine\block\Vine;
use pocketmine\entity\Effect;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\InputMode;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use Generator;

final class InboundHandle{

    /** @var Vector3[] */
    public $blockPlaceVectors = [];

    public function handle(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket){
            if(!$data->loggedIn) return;
            $location = Location::fromObject($packet->getPosition()->subtract(0, 1.62, 0), $data->player->getLevel());
            $data->boundingBox = AABB::fromPosition($location->asVector3());
            $data->lastLocation = clone $data->currentLocation;
            $data->currentLocation = $location;
            $data->lastMoveDelta = $data->currentMoveDelta;
            $data->currentMoveDelta = $data->currentLocation->subtract($data->lastLocation)->asVector3();
            $data->previousYaw = $data->currentYaw; $data->previousPitch = $data->currentPitch;
            $data->currentYaw = $packet->getYaw(); $data->currentPitch = $packet->getPitch();
            $data->lastYawDelta = $data->currentYawDelta; $data->lastPitchDelta = $data->currentPitchDelta;
            $data->currentYawDelta = abs(abs($data->currentYaw) - abs($data->previousYaw));
            $data->currentPitchDelta = abs(abs($data->currentPitch) - abs($data->previousPitch));
            $data->directionVector = MathUtils::directionVectorFromValues($data->currentYaw, $data->currentPitch);
            $expectedMoveY = ($data->lastMoveDelta->y - MovementConstants::Y_SUBTRACTION) * MovementConstants::Y_MULTIPLICATION;
            $actualMoveY = $data->currentMoveDelta->y;
            $flag1 = abs($expectedMoveY - $actualMoveY) > 0.001;
            $flag2 = $expectedMoveY < 0;
            $data->groundCollision = count($data->player->getLevel()->getCollisionBlocks($data->boundingBox->expandedCopy(0.1, 0.2, 0.1)), true) !== 0;
            // this method is too sensitive
            $data->onGround = $data->groundCollision /*&& $flag1 && $flag2*/;
            /* if($data->currentMoveDelta->lengthSquared() > 0.0009){
                $f1 = var_export($flag1, true);
                $f2 = var_export($flag2, true);
                $c = var_export($data->groundCollision, true);
                $data->player->sendMessage("collision=$c flag1=$f1 flag2=$f2");
            } */
            $blockVector = new Vector3((int) round($location->x), (int) round($location->y - 1), (int) round($location->z));
            $possibleGhostBlock = (fmod(round($data->currentLocation->y, 4), MovementConstants::GROUND_MODULO) === 0.0 || fmod(round($data->currentLocation->y, 6) - 0.00001, MovementConstants::GROUND_MODULO) === 0.0) && !$data->onGround && $flag1 && $flag2;
            if($possibleGhostBlock){
                $possibleGhostBlock = false;
                for($x = -1; $x <= 1; $x++){
                    if($possibleGhostBlock) break;
                    for($z = -1; $z <= 1; $z++){
                        $newBlockVector = $blockVector->add($x, 0, $z);
                        $possibleGhostBlock = in_array($newBlockVector, $this->blockPlaceVectors);
                        if($possibleGhostBlock) break;
                    }
                }
            }
            if($possibleGhostBlock){
                /** @var Vector3 $newBlockVector */
                $pk = new UpdateBlockPacket();
                $pk->x = $newBlockVector->x;
                $pk->y = $newBlockVector->y;
                $pk->z = $newBlockVector->z;
                $pk->blockRuntimeId = 134;
                $pk->flags = UpdateBlockPacket::FLAG_ALL_PRIORITY;
                $pk->dataLayerId = UpdateBlockPacket::DATA_LAYER_NORMAL;
                $data->player->batchDataPacket($pk);
                NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($data, $blockVector) : void{
                    foreach($this->blockPlaceVectors as $key => $vector){
                        if($vector->equals($blockVector)){
                            unset($this->blockPlaceVectors[$key]);
                        }
                    }
                });
                $data->onGround = true;
            }
            if($data->onGround){
                $data->lastOnGroundLocation = $data->currentLocation;
            }
            $data->isCollidedVertically = $flag1;
            $data->isCollidedHorizontally = count($location->getLevel()->getCollisionBlocks($data->boundingBox->expandedCopy(0.5, 0.0, 0.5), true)) !== 0;
            $data->hasBlockAbove = $flag1 && $expectedMoveY > 0;

            $inset = 0.001; //Offset against floating-point errors
            $minX = (int) floor($data->boundingBox->minX + 0.5 + $inset);
            $minY = (int) floor($data->boundingBox->minY + $inset);
            $minZ = (int) floor($data->boundingBox->minZ + 0.5 + $inset);
            $maxX = (int) floor($data->boundingBox->maxX - 0.5 - $inset);
            $maxY = (int) floor($data->boundingBox->maxY - $inset);
            $maxZ = (int) floor($data->boundingBox->maxZ - 0.5 - $inset);
            $liquids = 0; $climbable = 0; $cobweb = 0;
            for($z = $minZ; $z <= $maxZ; ++$z){
                for($x = $minX; $x <= $maxX; ++$x){
                    for($y = $minY; $y <= $maxY; ++$y){
                        $block = $location->getLevel()->getBlockAt($x, $y, $z);
                        if($block->hasEntityCollision()){
                            if($block instanceof Liquid) $liquids++;
                            elseif($block instanceof Cobweb) $cobweb++;
                            elseif($block instanceof Ladder || $block instanceof Vine) $climbable++;
                        }
                    }
                }
            }

            if($liquids > 0) $data->ticksSinceInLiquid = 0;
            else ++$data->ticksSinceInLiquid;
            if($cobweb > 0) $data->ticksSinceInCobweb = 0;
            else ++$data->ticksSinceInCobweb;
            if($climbable > 0) $data->ticksSinceInClimbable = 0;
            else ++$data->ticksSinceInClimbable;


            if($data->onGround){
                ++$data->onGroundTicks;
                $data->offGroundTicks = 0;
            } else {
                ++$data->offGroundTicks;
                $data->onGroundTicks = 0;
            }
            ++$data->timeSinceAttack;
            ++$data->timeSinceMotion;
            ++$data->timeSinceJump;

            // handle movement so that PMMP doesn't shit itself
            if($data->currentMoveDelta->lengthSquared() > 0.0009 || $data->currentYawDelta > 0.0 || $data->currentPitchDelta > 0.0){
                $movePK = new MovePlayerPacket();
                $movePK->entityRuntimeId = $data->player->getId();
                $movePK->position = $packet->getPosition();
                $movePK->yaw = $packet->getYaw();
                $movePK->headYaw = $packet->getHeadYaw();
                $movePK->pitch = $packet->getPitch();
                $data->player->handleMovePlayer($movePK);
            }
            ++$data->currentTick;
            $data->inputMode = $packet->getInputMode();
            $data->isTouch = ($data->inputMode === InputMode::TOUCHSCREEN);
            $data->entityLocationMap->executeTick($data);
            foreach($data->effects as $effectData){
                if($effectData !== null){
                    --$effectData->ticksRemaining;
                    if($effectData->ticksRemaining <= 0){
                        switch($effectData->effectId){
                            case Effect::JUMP_BOOST:
                                $data->jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;
                                break;
                        }
                        unset($data->effects[$effectData->effectId]);
                    } else {
                        switch($effectData->effectId){
                            case Effect::JUMP_BOOST:
                                $data->jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION + ($effectData->amplifier / 10);
                                break;
                        }
                    }
                }
            }
            $await = $data->await[$data->currentTick] ?? null;
            if($await !== null && count($await) > 0){
                foreach($await as $callable) $callable();
            }
            unset($data->await[$data->currentTick]);
        } elseif($packet instanceof InventoryTransactionPacket){
            switch($packet->transactionType){
                case InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY:
                    switch($packet->trData->actionType){
                        case InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK:
                            $data->lastTargetEntity = $data->targetEntity;
                            $data->targetEntity = $data->player->getLevel()->getEntity($packet->trData->entityRuntimeId);
                            $data->timeSinceAttack = 0;
                            break;
                    }
                    $this->click($data);
                    break;
                case InventoryTransactionPacket::TYPE_USE_ITEM:
                    switch($packet->trData->actionType){
                        case InventoryTransactionPacket::USE_ITEM_ACTION_CLICK_BLOCK:
                            $clickedBlockPos = new Vector3($packet->trData->x, $packet->trData->y, $packet->trData->z);
                            $newBlockPos = $clickedBlockPos->getSide($packet->trData->face);
                            $block = $packet->trData->itemInHand->getBlock();
                            if($packet->trData->itemInHand->getId() < 0){
                                $block = new UnknownBlock($packet->trData->itemInHand->getId(), 0);
                            }
                            if($block->canBePlaced() || $block instanceof UnknownBlock){
                                $this->blockPlaceVectors[] = $newBlockPos;
                            }
                            break;
                    }
                    break;
            }
        } elseif($packet instanceof NetworkStackLatencyPacket){
            NetworkStackLatencyHandler::execute($data, $packet->timestamp);
        } elseif($packet instanceof LevelSoundEventPacket){
            if($packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE){
                $this->click($data);
            }
        } elseif($packet instanceof SetLocalPlayerAsInitializedPacket){
            $data->loggedIn = true;
        } elseif($packet instanceof PlayerActionPacket){
            switch($packet->action){
                case PlayerActionPacket::ACTION_START_SPRINT:
                    $data->isSprinting = true;
                    $data->airSpeed = MovementConstants::AIR_SPEED_SPRINT;
                    break;
                case PlayerActionPacket::ACTION_STOP_SPRINT:
                    $data->isSprinting = false;
                    $data->airSpeed = MovementConstants::AIR_SPEED_NORMAL;
                    break;
                case PlayerActionPacket::ACTION_JUMP:
                    $data->timeSinceJump = 0;
                    break;
            }
        }
    }

    private function click(PlayerData $data){
        if(count($data->clickSamples) === 20){
            $data->clickSamples = [];
            $data->runClickChecks = false;
        }
        $data->clickSamples[] = $data->currentTick - $data->lastClickTick;
        if(count($data->clickSamples) === 20){
            try{
                $data->cps = 20 / MathUtils::getAverage($data->clickSamples);
            } catch(\ErrorException $e){
                $data->cps = INF;
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