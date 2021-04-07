<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\MoveActorDeltaPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PacketPool;

class PMMPListener implements Listener{

    public function quit(PlayerQuitEvent $event) : void{
        Esoteric::getInstance()->dataManager->remove($event->getPlayer());
    }

    /**
     * @param DataPacketReceiveEvent $event
     * @priority HIGHEST
     * @ignoreCancelled false
     */
    public function inbound(DataPacketReceiveEvent $event): void{
        $packet = $event->getPacket();
        $player = $event->getPlayer();
        $playerData = Esoteric::getInstance()->dataManager->get($player) ?? Esoteric::getInstance()->dataManager->add($player);
        $playerData->inboundProcessor->execute($packet, $playerData);
        foreach($playerData->checks as $check) if($check->enabled()) $check->inbound($packet, $playerData);
    }

    /**
     * @param DataPacketSendEvent $event
     * @priority LOWEST
     * @ignoreCancelled true
     */
    public function outbound(DataPacketSendEvent $event): void{
        $packet = $event->getPacket();
        $player = $event->getPlayer();
        $playerData = Esoteric::getInstance()->dataManager->get($player) ?? Esoteric::getInstance()->dataManager->add($player);
        if($packet instanceof BatchPacket){
            $locationList = [];
            $key = null;
            $gen = PacketUtils::getAllInBatch($packet);
            while(($buff = $gen->current()) !== null){
                $pk = PacketPool::getPacket($buff);
                try{
                    try{
                        $pk->decode();
                    } catch(\RuntimeException $e){
                        $gen->next();
                        continue;
                    }
                } catch(\LogicException $e){
                    $gen->next();
                    continue;
                }
                if(($pk instanceof MovePlayerPacket || $pk instanceof MoveActorDeltaPacket) && $pk->entityRuntimeId !== $playerData->player->getId()){
                    $locationList[] = clone $pk;
                } elseif($pk instanceof NetworkStackLatencyPacket){
                    $key = $pk->timestamp;
                }

                $playerData->outboundProcessor->execute($pk, $playerData);
                foreach($playerData->checks as $check) if($check->handleOut()) $check->outbound($pk, $playerData);
                $gen->next();
            }
            if($playerData->loggedIn){
                if($playerData->entityLocationMap->key !== null){
                    if($key !== $playerData->entityLocationMap->key && count($locationList) > 0){
                        $event->setCancelled();
                        foreach($locationList as $p){
                            $playerData->entityLocationMap->add($p);
                        }
                    }
                }
            }
        }
    }

}