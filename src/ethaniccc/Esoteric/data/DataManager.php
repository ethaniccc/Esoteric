<?php

namespace ethaniccc\Esoteric\data;

use pocketmine\Player;

final class DataManager{

    /** @var string<PlayerData> */
    private $list = [];

    /**
     * @param Player $player
     * @return PlayerData
     * Add new data for a player
     */
    public function add(Player $player) : PlayerData{
        $data = new PlayerData($player);
        $this->list[$data->hash] = $data;
        return $data;
    }

    /**
     * @param Player $player
     * Remove data for a player - this is most likely used when a player leaves the server to prevent
     * memory leaks.
     */
    public function remove(Player $player) : void{
        unset($this->list[spl_object_hash($player)]);
    }

    /**
     * @param Player $player
     * @return PlayerData|null
     * Get a player's data.
     */
    public function get(Player $player) : ?PlayerData{
        return $this->list[spl_object_hash($player)] ?? null;
    }

    /**
     * @return PlayerData[]
     * Get the whole list, added to prevent me needing to add this in the future.
     */
    public function getAll(){
        return $this->list;
    }

}