<?php

namespace ethaniccc\Esoteric\check\combat\autoclicker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class AutoClickerA extends Check {

	public function __construct() {
		parent::__construct("Autoclicker", "A", "Checks if the player's cps goes beyond a threshold", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ((($packet instanceof InventoryTransactionPacket && $packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) || ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE)) && $data->runClickChecks && $data->isClickDataIsValid) {
			if ($data->cps > $this->option("max_cps", 21)) {
				if (++$this->buffer >= 2) {
					$this->flag($data, ["cps" => round($data->cps, 2)]);
				}
				$this->buffer = min($this->buffer, 4);
			} else {
				$this->buffer = max($this->buffer - 0.25, 0);
			}
		}
	}

}