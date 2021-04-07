<?php

namespace ethaniccc\Esoteric\data;

use pocketmine\Player;

class PlayerDataManager {

	/** @var PlayerData[] */
	private $data = [];

	public function get(Player $player): ?PlayerData {
		return $this->data[spl_object_hash($player)] ?? null;
	}

	public function getFromName(string $username): ?PlayerData {
		$found = null;
		$name = strtolower($username);
		$delta = PHP_INT_MAX;
		foreach ($this->data as $data) {
			if (stripos($data->player->getName(), $name) === 0) {
				$curDelta = strlen($data->player->getName()) - strlen($name);
				if ($curDelta < $delta) {
					$found = $data;
					$delta = $curDelta;
				}
				if ($curDelta === 0) {
					break;
				}
			}
		}
		return $found;
	}

	public function getDirect(string $hash): ?PlayerData {
		return $this->data[$hash] ?? null;
	}

	public function add(Player $player): PlayerData {
		$data = new PlayerData($player);
		$this->data[$data->hash] = $data;
		return $data;
	}

	public function remove(Player $player): void {
		unset($this->data[spl_object_hash($player)]);
	}

	/**
	 * @return PlayerData[]
	 */
	public function getAll(): array {
		return $this->data;
	}

}