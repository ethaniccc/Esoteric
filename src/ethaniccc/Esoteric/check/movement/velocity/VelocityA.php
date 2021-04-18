<?php

namespace ethaniccc\Esoteric\check\movement\velocity;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

class VelocityA extends Check {

	private $yMotion = 0.0;

	public function __construct() {
		parent::__construct("Velocity", "A", "Checks if the user is taking an abnormal amount of vertical knockback.", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket) {
			if ($data->ticksSinceMotion <= 1) {
				$this->yMotion = $data->motion->y;
			}

			if ($this->yMotion > 0.005) {
				if ($data->hasBlockAbove || $data->immobile || !$data->player->isAlive()) {
					$this->yMotion = 0.0;
					$this->buffer = 0;
					return;
				}

				$percentage = ($data->currentMoveDelta->y / $this->yMotion) * 100;
				$diff = $data->currentMoveDelta->y - $this->yMotion;
				if ($diff < -4.26E-7 && !$data->teleported && !$data->hasBlockAbove && $data->ticksSinceInCobweb >= 5 && $data->ticksSinceFlight >= 10 && $data->ticksSinceInLiquid >= 5 && $data->ticksSinceInClimbable >= 5) {
					if (++$this->buffer > 4) {
						$this->flag($data, ["pct" => round($percentage, 5) . "%",]);
					}
					$this->buffer = min($this->buffer, 16);
				} elseif ($data->onGround) {
					// it's impossible for the client to say that they are on the ground after taking motion
					// regardless, give some leniency
					if (++$this->buffer >= 2) {
						$this->flag($data, ["pct" => round($percentage, 5) . "%",]);
					}
				} else {
					$this->buffer = 0;
					$this->reward();
				}

				$this->yMotion = ($this->yMotion - 0.08) * MovementConstants::Y_MULTIPLICATION;
			}
		}
	}

}