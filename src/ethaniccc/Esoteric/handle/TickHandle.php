<?php

namespace ethaniccc\Esoteric\handle;

use ethaniccc\Esoteric\data\PlayerData;

final class TickHandle{

    public function execute(PlayerData $data) : void{
        $data->entityLocationMap->send($data);
    }

}