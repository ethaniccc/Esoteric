<?php

namespace ethaniccc\Esoteric\check\movement\motion;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\data\sub\protocol\InputConstants;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\PlayerInputPacket;

class MotionC extends Check{

    public function __construct(){
        parent::__construct("Motion", "C", "Checks if the player follows friction rules on-ground", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket && $data->onGroundTicks >= 5 && $data->currentMoveDelta->lengthSquared() > 0.0009 && $data->timeSinceTeleport > 1 && $data->timeSinceFlight >= 10){
            $friction = MovementConstants::FRICTION;
            $blockFriction = null;
            $AABB = new AABB($data->currentLocation->x - 0.5, $data->currentLocation->y - 1, $data->currentLocation->z - 0.5, $data->currentLocation->x + 0.5, $data->currentLocation->y, $data->currentLocation->z + 0.5);
            $blocks = $data->player->getLevel()->getCollisionBlocks($AABB);
            foreach($blocks as $block){
                if($blockFriction === null){
                    $blockFriction = $block->getFrictionFactor();
                } elseif($block->getFrictionFactor() !== $blockFriction){
                    // two different blocks
                    $data->player->sendMessage("sus {$block->getFrictionFactor()} vs $blockFriction");
                    return;
                }
            }
            if($blockFriction === null){
                // assume normal block friction
                $blockFriction = 0.6;
            }
            $friction *= $blockFriction;
            $lastMoveDeltaXZ = MathUtils::hypot($data->lastMoveDelta->x, $data->lastMoveDelta->z);
            $currentMoveDeltaXZ = MathUtils::hypot($data->currentMoveDelta->x, $data->currentMoveDelta->z);
            $estimatedXZ = $lastMoveDeltaXZ * $friction;
            $diff = ($currentMoveDeltaXZ - $estimatedXZ) - $data->movementSpeed;
            if(!$data->isCollidedHorizontally && $data->timeSinceJump > 1 && !$data->hasBlockAbove){
                if($diff > 0.025){
                    // bad boi - most of the time, the difference is negative
                    // $data->player->sendMessage("diff=$diff");
                    // if this turns out to still false positive, I'll put back the buffer.
                    $this->flag($data, [
                        "diff" => round($diff, 3)
                    ]);
                    $this->setback($data);
                } else {
                    $this->reward();
                }
                // $data->player->sendMessage("diff=$diff movementSpeed={$data->movementSpeed}");
            }
        }
    }

}