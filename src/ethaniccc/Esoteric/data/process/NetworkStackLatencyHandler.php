<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\utils\BinaryStream;
use function mt_rand;

final class NetworkStackLatencyHandler {

	private static $instance = null;
	private $list = [];
	private $currentTimestamp = [];

	public static function getInstance(): self {
		if (self::$instance === null) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	private function next(PlayerData $data): NetworkStackLatencyPacket {
		if (!isset($this->currentTimestamp[$data->hash])) {
			$this->currentTimestamp[$data->hash] = 0;
		}
		$this->currentTimestamp[$data->hash] += mt_rand(1, 10) * 1000;
		$pk = new NetworkStackLatencyPacket();
		$pk->needResponse = true;
		$pk->timestamp = $this->currentTimestamp[$data->hash];
		return $pk;
	}

	public function send(PlayerData $data, callable $onResponse) {
		$packet = $this->next($data);
		$timestamp = $packet->timestamp;
		$pk = new BatchPacket();
		$pk->addPacket($packet);
		$pk->encode();
		$current = microtime(true) * 1000;
		PacketUtils::sendPacketSilent($data, $pk, true, static function (int $ackID) use ($data, $timestamp, $current): void {
			$data->tickProcessor->waiting[$timestamp] = $data->currentTick;
			$data->networkLatency = ceil((microtime(true) * 1000) - $current);
		});
		if (!isset($this->list[$data->hash])) {
			$this->list[$data->hash] = [];
		}
		$this->list[$data->hash][$timestamp] = $onResponse;
	}

	public function forceHandle(PlayerData $data, int $timestamp, callable $onResponse): void {
		if (!isset($this->list[$data->hash])) {
			$this->list[$data->hash] = [];
		}
		$this->list[$data->hash][$timestamp] = $onResponse;
	}

	public function execute(PlayerData $data, int $timestamp): void {
		$closure = $this->list[$data->hash][$timestamp] ?? null;
		if ($closure !== null) {
			$data->tickProcessor->response($timestamp);
			$closure($timestamp);
			unset($this->list[$data->hash][$timestamp]);
		}
	}

	public function remove(string $hash): void {
		unset($this->list[$hash]);
	}

}