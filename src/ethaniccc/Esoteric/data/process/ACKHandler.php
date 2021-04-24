<?php

namespace ethaniccc\Esoteric\data\process;

class ACKHandler {

	private static $list = [];
	private static $ackList = [];

	public static function add(string $identifier, int $ackID, callable $receive): void {
		if (!isset(self::$list[$identifier])) {
			self::$list[$identifier] = [];
		}
		self::$list[$identifier][$ackID] = $receive;
	}

	public static function execute(string $identifier, int $ackID): void {
		$callable = self::$list[$identifier][$ackID] ?? null;
		if ($callable !== null) {
			$callable($ackID);
			unset(self::$list[$identifier][$ackID]);
		}
	}

	public static function next(string $identifier): int {
		if (!isset(self::$ackList[$identifier])) {
			self::$ackList[$identifier] = 0;
		}
		return ++self::$ackList[$identifier];
	}

}