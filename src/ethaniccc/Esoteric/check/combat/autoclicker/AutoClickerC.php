<?php

namespace ethaniccc\Esoteric\check\combat\autoclicker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class AutoClickerC extends Check {

	public function __construct() {
		parent::__construct("Autoclicker", "C", "Checks if the player is clicking over 16 cps with no double clicks");
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ((($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) || ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE)) && $data->runClickChecks) {
			$this->debug($data, "samples=[" . implode(",", $data->clickSamples) . "]");
			if ($data->cps >= 16 && !in_array(0, $data->clickSamples, true)) {
				$this->flag($data, ["cps" => round($data->cps, 2)]);
			}
		}
	}

}