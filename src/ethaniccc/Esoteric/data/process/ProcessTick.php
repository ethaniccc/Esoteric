<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;

class ProcessTick {

	public function execute(PlayerData $data): void {
		if ($data->loggedIn) {
			$data->entityLocationMap->send($data);
			NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data): void {
				$data->currentTick++;
				$data->tick();
			});
		}
	}

}