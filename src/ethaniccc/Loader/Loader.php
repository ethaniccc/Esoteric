<?php

namespace ethaniccc\Loader;

use ethaniccc\Esoteric\Esoteric;
use Exception;
use pocketmine\plugin\PluginBase;

final class Loader extends PluginBase {

	public function onEnable() {
		try {
			Esoteric::init($this, $this->getConfig(), true);
		} catch (Exception $e) {
			$this->getLogger()->error("Unable to start Esoteric [{$e->getMessage()}]");
		}
	}

	public function onDisable() {
		try {
			Esoteric::getInstance()->stop();
		} catch (Exception $e) {
			$this->getLogger()->error("Unable to stop esoteric [{$e->getMessage()}]");
		}
	}
}
