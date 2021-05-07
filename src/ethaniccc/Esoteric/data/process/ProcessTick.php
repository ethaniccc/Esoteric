<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use function count;
use function floor;
use function max;
use function microtime;
use function min;
use function mt_rand;

class ProcessTick {

	public $waiting = [];

	public $currentTimestamp, $nextTimestamp;

	public function execute(PlayerData $data): void {
		if ($data->loggedIn && $data->playerOS !== DeviceOS::PLAYSTATION) {
			$data->entityLocationMap->send($data);
			NetworkStackLatencyHandler::getInstance()->send($data);
			if ($data->currentTick % 5 === 0) {
				$currentTime = microtime(true);
				$networkStackLatencyHandler = NetworkStackLatencyHandler::getInstance();
				$networkStackLatencyHandler->queue($data, function (int $timestamp) use ($data, $currentTime): void {
					$data->latency = floor((microtime(true) - $currentTime) * 1000);
				});
			}
			$timeoutSettings = Esoteric::getInstance()->getSettings()->getTimeoutSettings();
			if ($timeoutSettings["enabled"]) {
				if (count($this->waiting) >= $timeoutSettings["total_packets"] && ($tickDiff = max($this->waiting) - min($this->waiting)) >= $timeoutSettings["ticks"]) {
					$data->player->sendMessage("diff=$tickDiff count=" . count($this->waiting));
					//Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleTask(new KickTask($data->player, "Timed out (Contact a staff member if this issue persists)"));
				}
			} else {
				$this->waiting = [];
			}
			$this->currentTimestamp = $this->nextTimestamp;
			$this->nextTimestamp = mt_rand(1, 1000000000000000) * 1000;
		}
	}

	public function response(int $timestamp): void {
		unset($this->waiting[$timestamp]);
	}

	public function getLatencyTimestamp(): int {
		if ($this->currentTimestamp === null) {
			$this->currentTimestamp = mt_rand(1, 1000000000000000) * 1000;
			$this->nextTimestamp = mt_rand(1, 1000000000000000) * 1000;
		}
		return $this->currentTimestamp;
	}

}