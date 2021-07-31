<?php

namespace ethaniccc\Esoteric\check\movement\velocity;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\TextFormat;

// This detection has not been tested on production.
class VelocityB extends Check {

	public function __construct() {
		parent::__construct("Velocity", "B", "Checks if the player takes an abnormal amount of horizontal knockback", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket && $data->ticksSinceMotion === 1 && $data->offGroundTicks > 2 && $data->ticksSinceTeleport >= 3 && $data->ticksSinceSpawn >= 20) { // TODO: Correct on-ground estimations
			$expectedMovement = clone $data->motion;
			if (abs($expectedMovement->x) <= 0.001 && abs($expectedMovement->z) <= 0.001) {
				return;
			}
			$f5 = $data->jumpMovementFactor;
			$mF = $data->moveForward;
			$mS = $data->moveStrafe;
			$force = ($mS ** 2) + ($mF ** 2);
			if ($force >= 1E-4) {
				$force = sqrt($force);
				if ($force < 1) {
					$force = 1;
				}
				$force = $f5 / $force;
				$mF *= $force;
				$mS *= $force;
				$f1 = sin($data->currentYaw * M_PI / 180);
				$f2 = cos($data->currentYaw * M_PI / 180);
				$expectedMovement->x += ($mS * $f2 - $mF * $f1);
				$expectedMovement->z += ($mF * $f2 + $mS * $f1);
			}
			$pct = (MathUtils::hypot($data->currentMoveDelta->x, $data->currentMoveDelta->z) / MathUtils::hypot($expectedMovement->x, $expectedMovement->z)) * 100;
			$xDiff = abs($data->currentMoveDelta->x - $expectedMovement->x);
			if ($xDiff > 0.01) {
				$color = TextFormat::RED;
			} elseif ($xDiff > 0.00001) {
				$color = TextFormat::YELLOW;
			} else {
				$color = TextFormat::GREEN;
			}
			$xDiff = $color . $xDiff . TextFormat::RESET;
			$zDiff = abs($data->currentMoveDelta->z - $expectedMovement->z);
			if ($zDiff > 0.01) {
				$color = TextFormat::RED;
			} elseif ($zDiff > 0.00001) {
				$color = TextFormat::YELLOW;
			} else {
				$color = TextFormat::GREEN;
			}
			$zDiff = $color . $zDiff . TextFormat::RESET;
			$keys = [];
			if ($data->moveForward > 0) {
				$keys[] = "W";
			} elseif ($data->moveForward < 0) {
				$keys[] = "S";
			}
			if ($data->moveStrafe > 0) {
				$keys[] = "A";
			} elseif ($data->moveStrafe < 0) {
				$keys[] = "D";
			}
			$keys = implode(",", $keys);
			$subVec = $data->currentMoveDelta->subtract($expectedMovement);
			if (!$data->isCollidedHorizontally && $data->ticksSinceInClimbable > 5 && $data->ticksSinceFlight > 5 && !$data->isGliding
			&& $data->ticksSinceInCobweb > 5 && $data->ticksSinceInLiquid > 5) {
				if (abs($subVec->x) > 5E-5 && abs($subVec->z) > 5E-5) {
					$r = round($pct, 3);
					$this->flag($data, ["pct" => "$r%", "keys" => $keys === "" ? "none" : $keys]);
				} else {
					$this->reward(0.05);
				}
			}
			$this->debug($data, "keys=$keys x=$xDiff z=$zDiff");
		}
	}

}