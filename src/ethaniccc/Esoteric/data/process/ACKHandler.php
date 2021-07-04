<?php

namespace ethaniccc\Esoteric\data\process;

use function is_null;

class ACKHandler {

	private array $list = [];
	private array $ackList = [];
	private static ?ACKHandler $instance = null;

	public static function getInstance(): self{
		if(is_null(self::$instance)){
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function add(string $identifier, int $ackID, callable $receive): void {
		$this->list[$identifier][$ackID] = $receive;
	}

	public function execute(string $identifier, int $ackID): void {
		$callable = $this->list[$identifier][$ackID] ?? null;
		if ($callable !== null) {
			$callable($ackID);
			unset($this->list[$identifier][$ackID]);
		}
	}

	public function next(string $identifier): int {
		if (!isset($this->ackList[$identifier])) {
			$this->ackList[$identifier] = 0;
		}
		return ++$this->ackList[$identifier];
	}

	public function remove(string $identifier): void {
		unset($this->list[$identifier]);
		unset($this->ackList[$identifier]);
	}

}
