<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\Esoteric;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\BatchPacket;
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
            $gen = $this->getAllInBatch($packet);
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
                $playerData->outboundProcessor->execute($pk, $playerData);
                foreach($playerData->checks as $check) if($check->handleOut()) $check->outbound($pk, $playerData);
                $gen->next();
            }
        }
    }

    private function getAllInBatch(BatchPacket $packet) : \Generator{
        $stream = new NetworkBinaryStream($packet->payload);
        while(!$stream->feof()){
            yield $stream->getString();
        }
    }

}