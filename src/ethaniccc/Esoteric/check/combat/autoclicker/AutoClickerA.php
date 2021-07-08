<?php

namespace ethaniccc\Esoteric\check\combat\autoclicker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use function max;
use function min;
use function round;

/**
 * Class AutoClickerA
 * @package ethaniccc\Esoteric\check\combat\autoclicker
 */
class AutoClickerA extends Check {

	/**
	 * AutoClickerA constructor.
	 */
	public function __construct() {
		parent::__construct("Autoclicker", "A", "Checks if the player's cps goes beyond a threshold", false);
	}

	/**
	 * @param DataPacket $packet
	 * @param PlayerData $data
	 */
	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ((($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) || ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE)) && $data->runClickChecks) {
			$max = $this->option("max_cps", 21);
			if ($data->cps > $max) {
				if (++$this->buffer >= 2) {
					$this->flag($data, ["cps" => round($data->cps, 2)]);
				}
				$this->buffer = min($this->buffer, 4);
			} else {
				$this->buffer = max($this->buffer - 0.25, 0);
			}
			$this->debug($data, "cps={$data->cps} max=$max");
		}
	}

}