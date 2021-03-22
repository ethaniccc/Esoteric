<?php

namespace ethaniccc\Esoteric\handle;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\listener\NetworkStackLatencyHandler;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\level\Location;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\InputMode;

final class InboundHandle{

    public function handle(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket){
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
            $expectedMoveY = ($data->lastMoveDelta->y - 0.08) * 0.980000012;
            $actualMoveY = $data->currentMoveDelta->y;
            $flag1 = abs($expectedMoveY - $actualMoveY) > 0.01;
            $flag2 = $expectedMoveY <= 0;
            $data->onGround = (fmod(round($data->currentLocation->y, 4), MathUtils::GROUND_MODULO) === 0.0 || fmod(round($data->currentLocation->y - 0.00001, 6), MathUtils::GROUND_MODULO) === 0.0 || count($data->player->getLevel()->getCollisionBlocks($data->boundingBox->expandedCopy(0, 0.2, 0), true)) !== 0) && $flag1 && $flag2;
            $data->isCollidedVertically = $flag1;
            $data->isCollidedHorizontally = count($location->getLevel()->getCollisionBlocks($data->boundingBox->expandedCopy(0.2, -0.1, 0.2), true)) !== 0;
            $data->hasBlockAbove = $flag1 && $expectedMoveY > 0;

            if($data->onGround){
                ++$data->onGroundTicks;
                $data->offGroundTicks = 0;
            } else {
                ++$data->offGroundTicks;
                $data->onGroundTicks = 0;
            }
            ++$data->timeSinceAttack;
            ++$data->timeSinceMotion;

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
            }
        } elseif($packet instanceof NetworkStackLatencyPacket){
            NetworkStackLatencyHandler::execute($data, $packet->timestamp);
        } elseif($packet instanceof LevelSoundEventPacket){
            if($packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE){
                $this->click($data);
            }
        } elseif($packet instanceof SetLocalPlayerAsInitializedPacket){
            $data->loggedIn = true;
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