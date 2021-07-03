<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\KickTask;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use function count;
use function floor;
use function is_null;
use function max;
use function microtime;
use function min;
use function mt_rand;

class ProcessTick {

	public array $waiting = [];
	public ?int $currentTimestamp = null;
	public int $nextTimestamp;
	public bool $timeoutEnabled;
	public int $totalTimeoutPackets;
	public int $timeoutTicks;

	public function __construct(){
		$timeoutSettings = Esoteric::getInstance()->getSettings()->getTimeoutSettings();
		$this->timeoutEnabled = $timeoutSettings['enabled'];
		$this->totalTimeoutPackets = $timeoutSettings['total_packets'];
		$this->timeoutTicks = $timeoutSettings['ticks'];
	}

	public function execute(PlayerData $data): void {
		if ($data->loggedIn && $data->playerOS !== DeviceOS::PLAYSTATION) {
			$data->entityLocationMap->send($data);
			if ($data->currentTick % 20 === 0) { // 5?
				$currentTime = microtime(true);
				$data->networkStackLatencyHandler->queue($data, function () use ($data, $currentTime) : void {
					$data->latency = floor((microtime(true) - $currentTime) * 1000);
				});
			}
			if ($this->timeoutEnabled) {
				$total = count($this->waiting);
				if ($total >= $this->totalTimeoutPackets) {
					$maxTickDiff = max($this->waiting) - min($this->waiting);
					if ($maxTickDiff >= $this->timeoutTicks) {
						Esoteric::getInstance()->getScheduler()->scheduleDelayedTask(new KickTask($data->player, "NetworkStackLatency timeout (tD=$maxTickDiff tACK=$total lMS=$data->latency)\nContact a staff member (send a screenshot of this) if this issue persists"), 1);
					}
				}
			} else {
				$this->waiting = [];
			}
			$this->randomizeTimestamps();
		}
	}

	public function response(int $timestamp): void {
		unset($this->waiting[$timestamp]);
	}

	public function getLatencyTimestamp(): int {
		if (is_null($this->currentTimestamp)) {
			$this->currentTimestamp = mt_rand(1, 1000000000000000) * 1000;
			$this->nextTimestamp = mt_rand(1, 1000000000000000) * 1000;
		}
		return $this->currentTimestamp;
	}

	public function randomizeTimestamps(): void {
		$this->currentTimestamp = $this->nextTimestamp;
		$this->nextTimestamp = mt_rand(1, 100000000000) * 1000;
	}

}