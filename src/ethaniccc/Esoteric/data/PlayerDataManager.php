<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\data\process\NetworkStackLatencyHandler;
use pocketmine\network\mcpe\NetworkSession;
use function is_null;
use function spl_object_hash;
use function stripos;
use function strlen;
use function strtolower;

class PlayerDataManager {

	/** @var PlayerData[] */
	private array $data = [];

	public function get(NetworkSession $session): ?PlayerData {
		return $this->data[spl_object_hash($session)] ?? null;
	}

	public function getFromName(string $username): ?PlayerData {
		$found = null;
		$name = strtolower($username);
		$delta = PHP_INT_MAX;
		foreach ($this->data as $data) {
			if (is_null($data->player)) continue;
			if (stripos($data->player->getName(), $name) === 0) {
				$curDelta = strlen($data->player->getName()) - strlen($name);
				if ($curDelta < $delta) {
					$found = $data;
					$delta = $curDelta;
				}
				if ($curDelta === 0) break;
			}
		}
		return $found;
	}

	public function getDirect(string $hash): ?PlayerData {
		return $this->data[$hash] ?? null;
	}

	public function add(NetworkSession $session): PlayerData {
		$data = new PlayerData($session);
		$this->data[$data->hash] = $data;
		return $data;
	}

	public function remove(NetworkSession $session): void {
		$hash = spl_object_hash($session);
		unset($this->data[$hash]);
		NetworkStackLatencyHandler::getInstance()->remove($hash);
	}

	/**
	 * @return PlayerData[]
	 */
	public function getAll(): array {
		return $this->data;
	}

}