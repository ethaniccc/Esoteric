<?php

namespace ethaniccc\Esoteric\utils;

use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\BatchPacket;

class PacketUtils{

    public static function getAllInBatch(BatchPacket $packet) : \Generator{
        $stream = new NetworkBinaryStream($packet->payload);
        while(!$stream->feof()){
            yield $stream->getString();
        }
    }

}