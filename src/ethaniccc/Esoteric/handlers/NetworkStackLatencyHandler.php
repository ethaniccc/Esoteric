<?php

namespace ethaniccc\Esoteric\handlers;

use ArrayObject;
use ErrorException;
use ethaniccc\Esoteric\thread\EsotericThread;
use Opis\Closure\SerializableClosure;
use RuntimeException;
use stdClass;
use Threaded;
use Volatile;
use function count;
use function get_class;
use function mt_rand;
use function serialize;
use function unserialize;
use function var_dump;
use const PHP_EOL;

final class NetworkStackLatencyHandler extends Volatile {

	/** @var int[] */
	private $currentTimestamps;
	/** @var Threaded[] */
	private $callableList;

	public function __construct() {
		$this->currentTimestamps = new Threaded();
		$this->callableList = new Volatile();
	}

	public function getCurrentTimestamp(string $identifier): int {
		if (!isset($this->currentTimestamps[$identifier])) {
			$this->callableList[$identifier] = [];
			$this->currentTimestamps[$identifier] = mt_rand(1, 1000000000) * 1000;
		}
		return $this->currentTimestamps[$identifier];
	}

	public function updateTimestamp(string $identifier): void {
		$this->currentTimestamps[$identifier] = mt_rand(1, 1000000000) * 1000;
	}

	public function queue(string $identifier, callable $run): void {
		if (!isset($this->callableList[$identifier])) {
			$this->callableList[$identifier] = new Volatile();
		}
		if (!isset($this->callableList[$identifier][$this->getCurrentTimestamp($identifier)])) {
			$this->callableList[$identifier][$this->getCurrentTimestamp($identifier)] = new Volatile();
		}
		$this->callableList[$identifier][$this->getCurrentTimestamp($identifier)][] = $run;
	}

	public function execute(string $identifier, int $timestamp): void {
		$subQueue = $this->callableList[$identifier] ?? null;
		if ($subQueue !== null) {
			$queue = $this->callableList[$identifier][$timestamp] ?? null;
			if ($queue !== null) {
				foreach ($queue as $callable) {
					$callable();
				}
			}
		}
		unset($this->callableList[$identifier][$timestamp]);
	}

}