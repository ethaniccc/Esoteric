<?php

namespace ethaniccc\Esoteric\check\combat\aim;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class AimB extends Check {

	public function __construct() {
		parent::__construct("Aim", "B", "Checks for rounded rotations", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket && $data->currentYawDelta > 0.0065) {
			$roundedDiff = abs(round($data->currentYawDelta, 1) - round($data->currentYawDelta, 5));
			if ($roundedDiff <= 3E-5) {
				if (++$this->buffer >= 3) {
					$this->flag($data, ["diff" => $roundedDiff]);
				}
			} else {
				$this->reward();
				$this->buffer = max($this->buffer - 0.05, 0);
			}
		}
	}

}