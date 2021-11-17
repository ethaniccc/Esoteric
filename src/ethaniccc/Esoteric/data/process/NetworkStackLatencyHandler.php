<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;

final class NetworkStackLatencyHandler{

	private static ?NetworkStackLatencyHandler $instance = null;
	private array $queue = [];

	public static function getInstance() : self{
		if(self::$instance === null){
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function queue(PlayerData $data, callable $onResponse) : void{
		$timestamp = $data->tickProcessor->getLatencyTimestamp();
		$this->send($data);
		$this->queue[$data->hash][$timestamp] = $onResponse;
	}

	public function execute(PlayerData $data, int $timestamp) : void{
		$callable = $this->queue[$data->hash][$timestamp] ?? null;
		if($callable !== null){
			($callable)($timestamp);
		}
		$data->tickProcessor->response($timestamp);
		unset($this->queue[$data->hash][$timestamp]);
	}

	public function remove(string $hash) : void{
		unset($this->queue[$hash]);
	}

	private function send(PlayerData $data) : void{
		$pk = new NetworkStackLatencyPacket();
		$pk->timestamp = $data->tickProcessor->currentTimestamp;
		$pk->needResponse = true;
		$data->player->getNetworkSession()->addToSendBuffer($pk);
		$data->tickProcessor->waiting[$pk->timestamp] = $data->currentTick;
		$data->tickProcessor->randomizeTimestamps();
	}

}