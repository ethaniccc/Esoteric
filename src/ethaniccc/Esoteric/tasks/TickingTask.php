<?php

namespace ethaniccc\Esoteric\tasks;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\scheduler\Task;

final class TickingTask extends Task{

    public function onRun(int $currentTick){
        if($currentTick % 20 === 0){
            Esoteric::getInstance()->hasAlerts = array_filter(Esoteric::getInstance()->dataManager->getAll(), function(PlayerData $data) : bool{
                return $data->player->isOnline() && $data->player->hasPermission("ac.alerts") && $data->hasAlerts;
            });
        }
        foreach(Esoteric::getInstance()->dataManager->getAll() as $pd){
            // TODO: Add more to the tick handler, such as checking for NetworkStackLatency responses
            $pd->tickHandler->execute($pd);
        }
    }

}