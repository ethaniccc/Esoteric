<?php

namespace ethaniccc\Esoteric\check\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use function round;

class MotionC extends Check {

	public function __construct() {
		parent::__construct("Motion", "C", "Checks if the player follows friction rules on-ground", false);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket && $data->onGroundTicks >= 6 && $data->ticksSinceFlight >= 10 && $data->inLoadedChunk && $data->ticksSinceTeleport > 3) {
			$friction = MovementConstants::FRICTION;
			$blockFriction = null;
			foreach ($data->blocksBelow as $block) {
				if ($blockFriction === null) {
					$blockFriction = $block->getFrictionFactor();
				} elseif ($block->getFrictionFactor() !== $blockFriction) {
					// two different blocks
					// $data->player->sendMessage("sus {$block->getFrictionFactor()} vs $blockFriction");
					return;
				}
			}
			if ($blockFriction === null) {
				// assume normal block friction (maybe a ghost block?)
				$blockFriction = 0.6;
			}
			$friction *= $blockFriction;
			$lastMoveDeltaXZ = MathUtils::hypot($data->lastMoveDelta->x, $data->lastMoveDelta->z);
			$currentMoveDeltaXZ = MathUtils::hypot($data->currentMoveDelta->x, $data->currentMoveDelta->z);
			$estimatedXZ = $lastMoveDeltaXZ * $friction;
			$diff = ($currentMoveDeltaXZ - $estimatedXZ) - $data->movementSpeed;
			if (!$data->isCollidedHorizontally) {
				if ($diff > 0.03) {
					// bad boi - most of the time, the difference is negative
					// $data->player->sendMessage("diff=$diff");
					// if this turns out to still false positive, I'll put back the buffer.
					$this->flag($data, ["diff" => round($diff, 3)]);
					$this->setback($data);
				} else {
					$this->reward();
				}
				// $data->player->sendMessage("diff=$diff movementSpeed={$data->movementSpeed}");
			}
		}
	}

}