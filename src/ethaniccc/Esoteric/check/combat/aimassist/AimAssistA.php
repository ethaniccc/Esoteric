<?php

namespace ethaniccc\Esoteric\check\combat\aimassist;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\EvictingList;
use ethaniccc\Esoteric\utils\Pair;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

class AimAssistA extends Check{

    private $graph;

    public function __construct(){
        parent::__construct("AimAssist", "A", "Checks for the coefficient of determination in a graph", true);
        $this->graph = new EvictingList(20);
    }

    public function inbound(DataPacket $packet, PlayerData $data) : void{
        if($packet instanceof PlayerAuthInputPacket && $data->targetEntity !== null){
            $locationData = $data->entityLocationMap->get($data->targetEntity->getId());
            if($locationData !== null && $locationData->isSynced){
                $location = $locationData->lastLocation;

                $xDist = $location->x - $data->currentLocation->x;
                $zDist = $location->z - $data->currentLocation->z;
                $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
                if($yaw < 0){
                    $yaw += 360.0;
                }
                $expectedYaw = fmod($yaw, 180);

                $expectedDelta = abs(abs($expectedYaw) - abs($data->previousYaw));
                $givenDelta = $data->currentYawDelta;

                // $data->player->sendMessage("expected=$expectedDelta current=$givenDelta");

                $this->graph->add(new Pair($expectedDelta, $givenDelta));

                if($this->graph->full()){
                    try{
                        $x = $y = $xy = $x2 = $y2 = 0.0;
                        foreach($this->graph->getAll() as $pair){
                            /** @var Pair $pair */
                            $x += $pair->getX();
                            $y += $pair->getY();
                        }
                        $meanX = $x / $this->graph->size();
                        $meanY = $y / $this->graph->size();
                        foreach($this->graph->getAll() as $pair){
                            /** @var Pair $pair */
                            $x = $pair->getX() - $meanX;
                            $y = $pair->getY() - $meanY;
                            $x2 += $x ** 2;
                            $y2 += $y ** 2;
                            $xy += $x * $y;
                        }
                        $r = $xy / sqrt($x2 * $y2);
                    } catch(\ErrorException $e){
                        $r = 0.0;
                    }
                    if($r >= 0.98) $this->flag($data, ["r" => round($r, 3)]);
                }
            }
        }
    }

}