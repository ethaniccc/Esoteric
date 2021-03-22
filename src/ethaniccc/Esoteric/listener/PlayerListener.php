<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\Esoteric;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;

final class PlayerListener implements Listener{

    public function receive(DataPacketReceiveEvent $event) : void{
        $player = $event->getPlayer();
        $packet = $event->getPacket();
        $data = Esoteric::getInstance()->dataManager->get($player) ?? Esoteric::getInstance()->dataManager->add($player);
        $data->inboundHandler->handle($packet, $data);
        foreach($data->checks as $check) if($check->enabled()) $check->inbound($packet, $data);

        // debug spam is annoying
        if($packet instanceof PlayerAuthInputPacket){
            $event->setCancelled();
        }
    }

    public function join(PlayerJoinEvent $event) : void{
        $player = $event->getPlayer();
        if($player->hasPermission("ac.alerts"))
            Esoteric::getInstance()->dataManager->get($player)->hasAlerts = true;
    }

    public function leave(PlayerQuitEvent $event) : void{
        Esoteric::getInstance()->dataManager->remove($event->getPlayer());
    }

    public function send(DataPacketSendEvent $event) : void{
        $player = $event->getPlayer();
        $packet = $event->getPacket();
        $data = Esoteric::getInstance()->dataManager->get($player) ?? Esoteric::getInstance()->dataManager->add($player);
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
                $data->outboundHandler->handle($pk, $data);
                foreach($data->checks as $check) if($check->handleOut()) $check->outbound($pk, $data);
                $gen->next();
            }
        } elseif($packet instanceof StartGamePacket){
            $packet->playerMovementType = PlayerMovementType::SERVER_AUTHORITATIVE_V2_REWIND;
        } elseif($packet instanceof MobEffectPacket){
            $data->outboundHandler->handle($packet, $data);
        }
    }

    private function getAllInBatch(BatchPacket $packet) : \Generator{
        $stream = new NetworkBinaryStream($packet->payload);
        while(!$stream->feof()){
            yield $stream->getString();
        }
    }

}