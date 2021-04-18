<?php

namespace ethaniccc\Esoteric\check\combat\aim;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class AimA extends Check {

	public function __construct() {
		parent::__construct("Aim", "A", "Checks for invalid headYaw to yaw patterns", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket) {
			$expectedHeadYaw = fmod(($packet->yaw > 0 ? 0 : 360) + $packet->yaw, 360);
			$diff = abs($expectedHeadYaw - $packet->headYaw);
			if ($diff > 5E-5 && $packet->headYaw > 0) {
				if (++$this->buffer >= 3) {
					$this->flag($data, ["diff" => ($diff >= 0.0001 ? round($diff, 4) : $diff)]);
				}
			} elseif ($packet->headYaw < 0) {
				$expectedHeadYaw = fmod($packet->headYaw, 180);
				$diff = abs($expectedHeadYaw - $packet->headYaw);
				if ($diff > 5E-5) {
					if (++$this->buffer >= 3) {
						$this->flag($data, ["diff" => ($diff >= 0.0001 ? round($diff, 4) : $diff)]);
					}
				} else {
					$this->buffer = max($this->buffer - 0.025, 0);
					$this->reward();
				}
			} else {
				$this->buffer = max($this->buffer - 0.025, 0);
				$this->reward();
			}
		}
	}

}