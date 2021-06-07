<?php

namespace ethaniccc\Esoteric\check\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\utils\MovementUtils;
use pocketmine\network\mcpe\protocol\DataPacket;
use function abs;

class MotionD extends Check {

	public function __construct() {
		parent::__construct("Motion", "D", "Checks for invalid movement with elytra", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			if ($data->isGliding) {
				$diffVec = $data->currentMoveDelta->subtract(MovementUtils::getEstimatedElytraMovement($data));
				if ((abs($diffVec->x) >= 0.01 && abs($diffVec->z) >= 0.01) || abs($diffVec->y) >= 0.08) {
					$this->flag($data, ["x" => round(abs($diffVec->x), 3), "y" => round(abs($diffVec->y), 3), "z" => round(abs($diffVec->z), 3)]);
				}
			}
		}
	}

}