<?php

namespace ethaniccc\Esoteric\check\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class MotionD extends Check {

	public function __construct() {
		parent::__construct("Motion", "D", "Checks if the jump speed exceeds a certain threshold", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket && $data->ticksSinceJump === 1 && !$data->teleported && $data->ticksSinceMotion > 4) {
			$currentXZ = MathUtils::hypot($data->currentMoveDelta->x, $data->currentMoveDelta->z);
			$last = $data->lastMoveDelta;
			if ($data->isSprinting) {
				$var = 0.017453292 * $data->currentYaw;
				$last->x -= sin($var) * 0.2;
				$last->z += cos($var) * 0.2;
			}
			$lastXZ = MathUtils::hypot($last->x, $last->z);
			$prediction = $lastXZ * MovementConstants::FRICTION + $data->movementSpeed;
			$diff = $currentXZ - $prediction;
			if ($diff >= 0.009) {
				$this->flag($data, ["diff" => round($diff, 4)]);
				$this->setback($data);
			}
		}
	}

}