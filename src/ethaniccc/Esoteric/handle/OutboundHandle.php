<?php

namespace ethaniccc\Esoteric\handle;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use ethaniccc\Esoteric\listener\NetworkStackLatencyHandler;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;

final class OutboundHandle{

    public function handle(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof MovePlayerPacket || $packet instanceof MoveActorDeltaPacket){
            if($packet->entityRuntimeId !== $data->player->getId()){
                $location = ($packet instanceof MovePlayerPacket ? $packet->position->subtract(0, 1.62, 0) : $packet->position);
                if($packet instanceof MovePlayerPacket && $packet->mode !== MovePlayerPacket::MODE_NORMAL){
                    $info = $data->entityLocationMap->get($packet->entityRuntimeId);
                    if($info !== null){
                        $info->newPosRotationIncrements = 0;
                        $info->isSynced = 0;
                    }
                }
                $data->entityLocationMap->add($location, $packet->entityRuntimeId);
            } else {
                if($packet->mode === MovePlayerPacket::MODE_TELEPORT){
                    $data->awaitingTeleport = true;
                    $level = $data->player->getLevel();
                    NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($packet, $data, $level) : void{
                        // $data->player->sendMessage("received teleport");
                        $data->awaitingTeleport = false;
                        $data->timeSinceTeleport = 0;
                        $data->teleportPos = Position::fromObject($packet->position->subtract(0, 1.62, 0), $level);
                    });
                }
            }
        } elseif($packet instanceof SetActorMotionPacket && $packet->entityRuntimeId === $data->player->getId()){
            NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($packet, $data) : void{
                $data->motion = clone $packet->motion;
                $data->timeSinceMotion = 0;
            });
        } elseif($packet instanceof UpdateBlockPacket){
            $blockVector = new Vector3($packet->x, $packet->y, $packet->z);
            if($packet->blockRuntimeId !== 134 && in_array($blockVector, $data->inboundHandler->blockPlaceVectors)){
                foreach($data->inboundHandler->blockPlaceVectors as $key => $vector){
                    if($blockVector->equals($vector)){
                        unset($data->inboundHandler->blockPlaceVectors[$key]);
                    }
                }
            }
        } elseif($packet instanceof MobEffectPacket){
            if($packet->entityRuntimeId === $data->player->getId()){
                switch($packet->eventId){
                    case MobEffectPacket::EVENT_ADD:
                        $effectData = new EffectData();
                        $effectData->effectId = $packet->effectId;
                        $effectData->amplifier = $packet->amplifier + 1;
                        $effectData->ticksRemaining = $packet->duration - 1;
                        NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($data, $effectData) : void{
                            $data->effects[$effectData->effectId] = $effectData;
                        });
                        break;
                    case MobEffectPacket::EVENT_MODIFY:
                        $effectData = $data->effects[$packet->effectId] ?? null;
                        if($effectData === null) return;
                        NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($effectData, $packet) : void{
                            $effectData->amplifier = $packet->amplifier + 1;
                            $effectData->ticksRemaining = $packet->duration - 1;
                        });
                        break;
                    case MobEffectPacket::EVENT_REMOVE:
                        NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($data, $packet) : void{
                            unset($data->effects[$packet->effectId]);
                        });
                        break;
                }
            }
        } elseif($packet instanceof AdventureSettingsPacket){
            if(!$packet->getFlag(AdventureSettingsPacket::ALLOW_FLIGHT)){
                NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use ($data) : void{
                    $data->isFlying = false;
                });
            }
        }/* elseif($packet instanceof UpdateAttributesPacket && $packet->entityRuntimeId === $data->player->getId()){
            foreach($packet->entries as $attribute){
                switch($attribute->getId()){
                    case Attribute::MOVEMENT_SPEED:
                        $data->movementSpeed = $attribute->getValue();
                        break;
                }
            }
        } */
    }

}