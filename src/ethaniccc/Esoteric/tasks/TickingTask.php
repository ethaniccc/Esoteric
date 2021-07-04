<?php

namespace ethaniccc\Esoteric\tasks;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\scheduler\Task;
use function array_filter;

class TickingTask extends Task {

	public function onRun(int $currentTick): void {
		if ($currentTick % 40 === 0) {
			Esoteric::getInstance()->hasAlerts = array_filter(Esoteric::getInstance()->dataManager->getAll(), static function (PlayerData $data): bool {
				return !$data->player->isClosed() && $data->hasAlerts && $data->player->hasPermission("ac.alerts");
			});
		}
		Esoteric::getInstance()->chunkThread->executeResults();
		foreach (Esoteric::getInstance()->dataManager->getAll() as $playerData) {
			$playerData->tickProcessor->execute($playerData);
		}
	}

}