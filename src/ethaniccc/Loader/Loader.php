<?php

namespace ethaniccc\Loader;

use ethaniccc\Esoteric\Esoteric;
use Exception;
use pocketmine\plugin\PluginBase;

/**
 * Class Loader
 * @package ethaniccc\Loader
 */
final class Loader extends PluginBase {


	public function onEnable(): void {
		try {
			Esoteric::init($this, $this->getConfig(), $this->getFile() . "vendor/autoload.php", true);
		} catch (Exception $e) {
			$this->getLogger()->error("Unable to start Esoteric [{$e->getMessage()}]");
			$this->getLogger()->logException($e);
		}
	}

	public function onDisable(): void {
		try {
			Esoteric::getInstance()->stop();
		} catch (Exception $e) {
			$this->getLogger()->error("Unable to stop esoteric [{$e->getMessage()}]");
			$this->getLogger()->logException($e);
		}
	}
}
