<?php

namespace ethaniccc\Esoteric;

use Exception;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

final class Loader extends PluginBase {

	public function onEnable(): void {
		try {
			Esoteric::init($this, $this->getConfig(), true);
		} catch (Exception $e) {
			$this->getLogger()->error("Unable to start Esoteric [{$e->getMessage()}]");
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick): void {
			$plugin = $this->getServer()->getPluginManager()->getPlugin("Mockingbird");
			if ($plugin !== null) {
				$this->getServer()->getPluginManager()->disablePlugin($plugin);
			}
		}), 1);
	}

	public function onDisable(): void {
		try {
			Esoteric::getInstance()->stop();
		} catch (Exception $e) {
			$this->getLogger()->error("Unable to stop esoteric [{$e->getMessage()}]");
		}
	}

}
