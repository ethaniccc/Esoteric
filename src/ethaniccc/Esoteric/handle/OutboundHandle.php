<?php

namespace ethaniccc\Esoteric\handle;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use ethaniccc\Esoteric\listener\NetworkStackLatencyHandler;
use pocketmine\entity\Attribute;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
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
                        $info->isSynced = false;
                    }
                }
                $data->entityLocationMap->add($location, $packet->entityRuntimeId);
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
                        $effectData->ticksRemaining = $packet->duration * 20;
                        NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($data, $effectData) : void{
                            $data->effects[$effectData->effectId] = $effectData;
                        });
                        break;
                    case MobEffectPacket::EVENT_MODIFY:
                        $effectData = $data->effects[$packet->effectId] ?? null;
                        if($effectData === null) throw new \UnexpectedValueException("WHAT THE FUCK? THE EFFECT DATA WAS NULL LOL");
                        NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($effectData, $packet) : void{
                            $effectData->amplifier = $packet->amplifier;
                            $effectData->ticksRemaining = $packet->duration * 20;
                        });
                        break;
                    case MobEffectPacket::EVENT_REMOVE:
                        NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($data, $packet) : void{
                            unset($data->effects[$packet->effectId]);
                        });
                        break;
                }
            }
        } elseif($packet instanceof UpdateAttributesPacket && $packet->entityRuntimeId === $data->player->getId()){
            foreach($packet->entries as $attribute){
                switch($attribute->getId()){
                    case Attribute::MOVEMENT_SPEED:
                        NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use($data, $attribute) : void{
                            $data->movementSpeed = $attribute->getValue();
                        });
                        break;
                }
            }
        }
    }

}