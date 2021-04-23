<?php

namespace ethaniccc\Esoteric\check\combat\aim;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class AimA extends Check {

	public function __construct() {
		parent::__construct("Aim", "A", "Checks for invalid headYaw to yaw patterns", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$expectedHeadYaw = fmod(($packet->getYaw() > 0 ? 0 : 360) + $packet->getYaw(), 360);
			$diff = abs($expectedHeadYaw - $packet->getHeadYaw());
			if ($diff > 5E-5 && $packet->getHeadYaw() > 0) {
				if (++$this->buffer >= 3) {
					$this->flag($data, ["diff" => ($diff >= 0.0001 ? round($diff, 4) : $diff)]);
				}
			} elseif ($packet->getHeadYaw() < 0) {
				$expectedHeadYaw = fmod($packet->getHeadYaw(), 180);
				$diff = abs($expectedHeadYaw - $packet->getHeadYaw());
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