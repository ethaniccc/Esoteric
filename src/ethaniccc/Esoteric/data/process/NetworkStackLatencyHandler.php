<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;

final class NetworkStackLatencyHandler {

	private static $instance = null;
	private $queue = [];

	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function queue(PlayerData $data, callable $onResponse) {
		$timestamp = $data->tickProcessor->getLatencyTimestamp();
		$this->queue[$data->hash][$timestamp][] = $onResponse;
	}

	public function execute(PlayerData $data, int $timestamp): void {
		$queue = $this->queue[$data->hash][$timestamp] ?? null;
		if ($queue !== null) {
			foreach ($queue as $run)
				$run($timestamp);
		}
		$data->tickProcessor->response($timestamp);
		unset($this->queue[$data->hash][$timestamp]);
	}

	public function send(PlayerData $data): void {
		$pk = new NetworkStackLatencyPacket();
		$pk->timestamp = $data->tickProcessor->currentTimestamp;
		$pk->needResponse = true;
		$data->player->getNetworkSession()->sendDataPacket($pk, true);
		$data->tickProcessor->waiting[$pk->timestamp] = $data->currentTick;
	}

	public function remove(string $hash): void {
		unset($this->queue[$hash]);
	}

}