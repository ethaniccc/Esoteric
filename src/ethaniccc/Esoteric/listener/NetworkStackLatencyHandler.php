<?php

namespace ethaniccc\Esoteric\listener;

use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;

final class NetworkStackLatencyHandler{

    private static $list = [];

    public static function random(bool $needsResponse = true) : NetworkStackLatencyPacket{
        $pk = new NetworkStackLatencyPacket();
        $pk->needResponse = $needsResponse; $pk->timestamp = mt_rand(1, 1000000000000000) * 1000;
        return $pk;
    }

    public static function send(PlayerData $data, NetworkStackLatencyPacket $packet, callable $onResponse) : void{
        if($packet->needResponse && $data->loggedIn){
            $timestamp = $packet->timestamp;
            $data->player->dataPacket($packet);
            if(!isset(self::$list[$data->hash])){
                self::$list[$data->hash] = [];
            }
            self::$list[$data->hash][$timestamp] = $onResponse;
        }
    }

    public static function execute(PlayerData $data, int $timestamp) : void{
        $closure = self::$list[$data->hash][$timestamp] ?? null;
        if($closure !== null){
            $closure($timestamp);
            unset(self::$list[$data->hash][$timestamp]);
        }
    }

    public static function remove(string $hash) : void{
        unset(self::$list[$hash]);
    }

}