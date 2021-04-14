<?php

namespace ethaniccc\Esoteric\utils;

final class GeneralUtils {

	public static function iterate($iterator, callable $callable): void {
		foreach ($iterator as $key => $val) {
			$callable($key, $val);
		}
	}

}