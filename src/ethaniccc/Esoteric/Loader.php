<?php

namespace ethaniccc\Esoteric;

use CortexPE\DiscordWebhookAPI\WebhookThread;
use Exception;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

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
		if (!Server::getInstance()->isRunning() && WebhookThread::valid()) {
			WebhookThread::getInstance()->stop();
		}
	}
}
