<?php

namespace ethaniccc\Esoteric\tasks;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\scheduler\Task;

class TickingTask extends Task {

	public function onRun(int $currentTick) {
		if ($currentTick % 40 === 0) {
			Esoteric::getInstance()->hasAlerts = array_filter(Esoteric::getInstance()->getDataManager()->getAll(), static function (PlayerData $data): bool {
				return !$data->player->isClosed() && $data->hasAlerts && $data->player->hasPermission("ac.alerts");
			});
		}
		foreach (Esoteric::getInstance()->getDataManager()->getAll() as $playerData) {
			$playerData->tickProcessor->execute($playerData);
		}
	}

}