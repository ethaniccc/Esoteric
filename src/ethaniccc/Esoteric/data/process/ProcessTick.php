<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\KickTask;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use function count;
use function floor;
use function max;
use function microtime;
use function min;

class ProcessTick {

	public $invalid = [];
	public $waiting = [];

	public function execute(PlayerData $data): void {
		if ($data->loggedIn && $data->playerOS !== DeviceOS::PLAYSTATION) {
			$data->entityLocationMap->send($data);
			if ($data->currentTick % 5 === 0) {
				$currentTime = microtime(true);
				NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::next($data), function (int $timestamp) use ($data, $currentTime): void {
					$data->latency = floor((microtime(true) - $currentTime) * 1000);
				});
			}
			foreach ($this->invalid as $key) {
				if (isset($this->waiting[$key])) {
					// packet order 9000, no?
					unset($this->waiting[$key]);
					unset($this->invalid[$key]);
				}
			}
			$timeoutSettings = Esoteric::getInstance()->getSettings()->getTimeoutSettings();
			if ($timeoutSettings["enabled"]) {
				$total = count($this->waiting);
				if ($total >= $timeoutSettings["total_packets"]) {
					$maxTickDiff = max($this->waiting) - min($this->waiting);
					if ($maxTickDiff >= $timeoutSettings["ticks"]) {
						Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleDelayedTask(new KickTask($data->player, "NetworkStackLatency timeout (tD=$maxTickDiff tACK=$total lMS={$data->latency})\nContact a staff member (send a screenshot of this) if this issue persists"), 1);
					}
				}
			} else {
				$this->invalid = [];
				$this->waiting = [];
			}
		}
	}

	public function response(int $timestamp): void {
		if (isset($this->waiting[$timestamp])) {
			unset($this->waiting[$timestamp]);
		} else {
			$this->invalid[] = $timestamp;
		}
	}

}