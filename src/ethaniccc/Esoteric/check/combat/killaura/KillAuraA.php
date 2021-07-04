<?php

namespace ethaniccc\Esoteric\check\combat\killaura;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\Server;

class KillAuraA extends Check {

	private int $lastTick;

	public function __construct() {
		parent::__construct("Killaura", "A", "Checks if the player is swinging their arm while attacking", false);
		$this->lastTick = Server::getInstance()->getTick();
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data): void {
		if ($packet instanceof AnimatePacket && $packet->action === AnimatePacket::ACTION_SWING_ARM) {
			$this->lastTick = $data->currentTick;
		} elseif ($packet instanceof InventoryTransactionPacket && $packet->trData instanceof UseItemOnEntityTransactionData && $packet->trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK) {
			$tickDiff = $data->currentTick - $this->lastTick;
			if ($tickDiff > 4) $this->flag($data, ['tickDiff' => $tickDiff]);
		}
	}

}