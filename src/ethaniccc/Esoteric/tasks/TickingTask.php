<?php

namespace ethaniccc\Esoteric\tasks;

use ethaniccc\Esoteric\Esoteric;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use const PHP_EOL;

class TickingTask extends Task {

	public function onRun(): void {
		foreach (Server::getInstance()->getOnlinePlayers() as $player) {
			$identifier = "{$player->getNetworkSession()->getIp()} {$player->getNetworkSession()->getPort()}";
			$currentTimestamp = Esoteric::getInstance()->thread->getCurrentTimestamp($identifier);
			if ($currentTimestamp !== -1) {
				$pk = new NetworkStackLatencyPacket();
				$pk->timestamp = $currentTimestamp;
				$pk->needResponse = true;
				$player->getNetworkSession()->sendDataPacket($pk, true);
			}
		}
		Esoteric::getInstance()->thread->updateTimestamps();
	}

}