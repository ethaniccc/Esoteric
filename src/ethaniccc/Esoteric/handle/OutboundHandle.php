<?php

namespace ethaniccc\Esoteric\handle;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\listener\NetworkStackLatencyHandler;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;

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
        }
    }

}