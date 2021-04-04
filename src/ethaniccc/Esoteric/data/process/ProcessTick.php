<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;

class ProcessTick{

    private $lastTime;

    public function execute(PlayerData $data) : void{
        if($data->loggedIn){
            if($this->lastTime === null){
                $this->lastTime = microtime(true);
                NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use ($data): void{
                    $data->currentTick++;
                    $data->tick();
                });
            } else {
                $time = microtime(true);
                $ticks = (int) ceil($time - $this->lastTime);
                while($ticks !== 0){
                    NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function(int $timestamp) use ($data): void{
                        $data->currentTick++;
                        $data->tick();
                    });
                    $ticks--;
                }
                $this->lastTime = $time;
            }
        }
    }

}