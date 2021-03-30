<?php

namespace ethaniccc\Esoteric\check\movement\phase;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\LevelUtils;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use ethaniccc\Esoteric\utils\AABB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;

class PhaseA extends Check{

    /** @var Vector3 */
    private $lastSafeLocation;

    public function __construct(){
        parent::__construct("Phase", "A", "This check does not flag. This check will setback the player if the player is suspected to be phasing into a block illegally.", false);
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if($packet instanceof PlayerAuthInputPacket){
            if($data->currentMoveDelta->lengthSquared() > 0.0009){
                $currentAABB = AABB::fromPosition($data->currentLocation)->expand(-0.1, 0.0, -0.1);
                $previousAABB = AABB::fromPosition($data->lastLocation)->expand(-0.1, 0.0, -0.1);
                // rip performance
                $currentHasCollision = count($data->player->getLevel()->getCollisionBlocks($currentAABB, true)) !== 0;
                $previousHasCollision = count($data->player->getLevel()->getCollisionBlocks($previousAABB, true)) !== 0;
            } else {
                $currentHasCollision = false;
                $previousHasCollision = false;
            }
            if(!$previousHasCollision && $data->currentMoveDelta->length() > 0.0025){
                $this->lastSafeLocation = clone $data->lastLocation;
            }
            // $data->player->sendMessage("current=" . var_export($currentHasCollision, true) . " previous=" . var_export($previousHasCollision, true));
            if($currentHasCollision && $previousHasCollision && $data->currentMoveDelta->lengthSquared() > 0 && $data->currentMoveDelta->y <= 0){
                $data->player->teleport($this->lastSafeLocation ?? $data->lastLocation, $data->currentYaw, $data->currentPitch);
            }
        }
    }

}