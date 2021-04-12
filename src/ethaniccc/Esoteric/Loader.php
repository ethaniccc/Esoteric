<?php

namespace ethaniccc\Esoteric;

use CortexPE\DiscordWebhookAPI\WebhookThread;
use ethaniccc\Esoteric\tasks\ExecuteWebhookTask;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

final class Loader extends PluginBase {

	public function onEnable() {
		$this->getServer()->getAsyncPool()->submitTask(new ExecuteWebhookTask("https://canary.discord.com/api/webhooks/830465747570262018/j-Dl5UPtkOuRFTn5g8MoGhQFbcNfbxlNhR8rct7luj1ZHoVBoydU2zBSWLQNVxRY1F_Z", json_encode("{content: 'HELLO'}")), 5000);
		try {
			Esoteric::init($this, $this->getConfig(), true);
		} catch (\Exception $e) {
			$this->getLogger()->error("Unable to start Esoteric [{$e->getMessage()}]");
		}
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick): void {
			$plugin = $this->getServer()->getPluginManager()->getPlugin("Mockingbird");
			if ($plugin !== null)
				$this->getServer()->getPluginManager()->disablePlugin($plugin);
		}), 1);
	}

	public function onDisable() {
		try {
			Esoteric::getInstance()->stop();
		} catch (\Exception $e) {
			$this->getLogger()->error("Unable to stop esoteric [{$e->getMessage()}]");
		}
		if (!Server::getInstance()->isRunning()) {
			WebhookThread::getInstance()->stop();
		}
	}
}
