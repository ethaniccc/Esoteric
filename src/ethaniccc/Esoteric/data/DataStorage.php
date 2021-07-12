<?php

namespace ethaniccc\Esoteric\data;

use pocketmine\Player;

final class DataStorage {

	/** @var UserData[] */
	private $storage = [];

	public function add(Player $player): UserData {
		$data = new UserData($player);
		$this->storage[spl_object_id($player)] = $data;
		return $data;
	}

	public function get(Player $player, bool $add = false): ?UserData {
		return $this->storage[spl_object_id($player)] ?? ($add ? $this->add($player) : null);
	}

	public function remove(Player $player): void {
		if (isset($this->storage[spl_object_id($player)])) {
			$this->storage[spl_object_id($player)]->destroy();
		}
		unset($this->storage[spl_object_id($player)]);
	}

}