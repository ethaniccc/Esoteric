<?php

namespace ethaniccc\Esoteric\handlers;

use Threaded;
use function mt_rand;

final class NetworkStackLatencyHandler extends Threaded {

	private $currentTimestamps = [];
	private $callableList = [];

	public function getCurrentTimestamp(string $identifier): int {
		if (!isset($this->currentTimestamps[$identifier])) {
			$this->currentTimestamps[$identifier] = mt_rand(1, 1000000000) * 1000;
		}
		return $this->currentTimestamps[$identifier];
	}

	public function updateTimestamp(string $identifier): void {
		$this->currentTimestamps[$identifier] = mt_rand(1, 1000000000) * 1000;
	}

	public function queue(string $identifier, callable $run): void {
		if (!isset($this->callableList[$identifier][$this->getCurrentTimestamp($identifier)])) {
			$this->callableList[$identifier][$this->getCurrentTimestamp($identifier)] = new Threaded();
		}
		$this->callableList[$identifier][$this->getCurrentTimestamp($identifier)][] = $run;
	}

	public function execute(string $identifier, int $timestamp): void {
		$queue = $this->callableList[$identifier][$timestamp] ?? null;
		if ($queue !== null) {
			foreach ($queue as $callable)
				$callable($timestamp);
		}
		unset($this->callableList[$identifier][$timestamp]);
	}

}