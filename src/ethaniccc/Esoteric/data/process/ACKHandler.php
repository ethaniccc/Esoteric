<?php

namespace ethaniccc\Esoteric\data\process;

class ACKHandler {

	private $list = [];
	private $ackList = [];
	private static $instance = null;

	public static function getInstance(): self{
		if(self::$instance === null){
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function add(string $identifier, int $ackID, callable $receive): void {
		if (!isset($this->list[$identifier])) {
			$this->list[$identifier] = [];
		}
		$this->list[$identifier][$ackID] = $receive;
	}

	public function execute(string $identifier, int $ackID): void {
		$callable = $this->list[$identifier][$ackID] ?? null;
		if ($callable !== null) {
			$callable($ackID);
			unset($this->list[$identifier][$ackID]);
		}
	}

	public function hasData(string $identifier): bool {
		return isset($this->list[$identifier]);
	}

	public function next(string $identifier): int {
		if (!isset($this->ackList[$identifier])) {
			$this->ackList[$identifier] = 0;
		}
		return ++$this->ackList[$identifier];
	}

}