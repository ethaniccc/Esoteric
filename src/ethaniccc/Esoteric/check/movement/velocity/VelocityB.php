<?php

namespace ethaniccc\Esoteric\check\movement\velocity;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class VelocityB extends Check {

	private $previousForward = 0.0;
	private $previousStrafe = 0.0;

	public function __construct() {
		parent::__construct("Velocity", "B", "Checks if the player takes an abnormal amount of horizontal knockback", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket) {
			if ($data->ticksSinceMotion === 1 && $data->motion->length() > 0) {
				$minPct = 99.75;
				if ($this->previousForward !== $data->moveForward || $this->previousStrafe !== $data->moveStrafe) {
					$minPct = 95.0;
				}
				$onGround = $data->offGroundTicks <= 1;
				$knockbackMotion = new Vector3($data->motion->x, 0, $data->motion->z);
				$friction = MovementConstants::FRICTION;
				if ($onGround) {
					$friction *= $data->player->getLevel()->getBlockAt($data->currentLocation->x, $data->currentLocation->y - 1, $data->currentLocation->z, false, false)->getFrictionFactor();
					$f = 1.3 * $data->movementSpeed * (0.16277136 / ($friction ** 3));
				} else {
					$f = $data->jumpMovementFactor;
				}
				$moveForward = $data->moveForward;
				$moveStrafe = $data->moveStrafe;
				$force = ($moveForward ** 2) + ($moveStrafe ** 2);
				if ($force >= 1E-4) {
					$force = $f / $force;
					$moveForward *= $force;
					$moveStrafe *= $force;
					$f1 = sin($data->currentYaw * M_PI / 180);
					$f2 = cos($data->currentYaw * M_PI / 180);
					$knockbackMotion->x += $moveStrafe * $f2 - $moveForward * $f1;
					$knockbackMotion->z += $moveForward * $f2 - $moveStrafe * $f1;
				}
				$pct = (MathUtils::hypot($data->currentMoveDelta->x, $data->currentMoveDelta->z) / $knockbackMotion->length()) * 100;
				if ($pct < $minPct) {
					if (++$this->buffer >= 4) {
						$this->flag($data, ["pct" => round($pct, 5) . "%", "xDiff" => $data->currentMoveDelta->x - $knockbackMotion->x, "zDiff" => $data->currentMoveDelta->z - $knockbackMotion->z, "min" => $minPct]);
					}
				} else {
					$this->buffer = max($this->buffer - ($data->onGround ? 0.1 : 0.25), 0);
					$this->reward(0.02);
				}
				//$data->player->sendMessage("mF={$data->moveForward} mS={$data->moveStrafe}");
				//$data->player->sendMessage("pct=$pct% onGround=" . var_export($onGround, true) . " min=$minPct fail=" . var_export($pct < $minPct, true));
			}
			$this->previousForward = $data->moveForward;
			$this->previousStrafe = $data->moveStrafe;
		}
	}

}