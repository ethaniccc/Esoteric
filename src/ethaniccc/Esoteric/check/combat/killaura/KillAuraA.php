<?php

namespace ethaniccc\Esoteric\check\combat\killaura;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\Server;

class KillAuraA extends Check {

	private $lastTick;

	public function __construct() {
		parent::__construct("Killaura", "A", "Checks if the player is swinging their arm while attacking", false);
		$this->lastTick = Server::getInstance()->getTick();
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof AnimatePacket && $packet->action === AnimatePacket::ACTION_SWING_ARM) {
			$this->lastTick = $data->currentTick;
		} elseif ($packet instanceof InventoryTransactionPacket && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY && $packet->trData->actionType === InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK) {
			$tickDiff = $data->currentTick - $this->lastTick;
			if ($tickDiff > 4) {
				$this->flag($data);
			}
		}
	}

}