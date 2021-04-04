<?php

namespace ethaniccc\Esoteric\check\combat\killaura;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\Server;

class KillAuraA extends Check{

    private $lastTick;

    public function __construct(){
        parent::__construct("Killaura", "A", "Checks if the player is swinging their arm while attacking", false);
        $this->lastTick = Server::getInstance()->getTick();
    }

    public function inbound(DataPacket $packet, PlayerData $data): void{
        if($packet instanceof AnimatePacket && $packet->action === AnimatePacket::ACTION_SWING_ARM){
            $this->lastTick = Server::getInstance()->getTick();
        } elseif($packet instanceof InventoryTransactionPacket && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->actionType === InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK){
            $tickDiff = Server::getInstance()->getTick() - $this->lastTick;
            if($tickDiff > 4){
                $this->flag($data);
            }
        } elseif($packet instanceof MovePlayerPacket && !$data->teleported){
            $expectedHeadYaw = MathUtils::getLiteralFloat(fmod(($packet->yaw > 0 ? 0 : 360) + $packet->yaw, 360));
            $diff = abs($expectedHeadYaw - $packet->headYaw);
            if($diff > 0.001){
                $data->player->sendMessage("headYaw={$packet->headYaw} expected=$expectedHeadYaw");
            } elseif($expectedHeadYaw < 0){
                $data->player->sendMessage("invalid negative head yaw");
            }
        }
    }

}