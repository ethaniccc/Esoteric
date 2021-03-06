<?php

namespace ethaniccc\Esoteric\check\combat\autoclicker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\EvictingList;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use function round;

/**
 * Class AutoClickerB
 * @package ethaniccc\Esoteric\check\combat\autoclicker
 */
class AutoClickerB extends Check {

	/**
	 * @var EvictingList
	 */
	private $samples;

	/**
	 * AutoClickerB constructor.
	 */
	public function __construct() {
		parent::__construct("Autoclicker", "B", "Checks for duplicated statistical values in clicks", true);
		$this->samples = new EvictingList($this->option("samples", 10));
	}

	/**
	 * @param DataPacket $packet
	 * @param PlayerData $data
	 */
	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ((($packet instanceof InventoryTransactionPacket && $packet->trData->getTypeId() === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) || ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE)) && $data->runClickChecks) {
			$this->samples->add("kurtosis={$data->kurtosis} skewness={$data->skewness} outliers={$data->outliers}");
			$duplicates = $this->samples->duplicates();
			if ($this->samples->full() && $duplicates >= $this->option("max_duplicates", 4) && $data->cps > 10) {
				$this->flag($data, ["duplicates" => $duplicates, "cps" => round($data->cps, 2)]);
				$this->samples->clear();
			}
			$this->debug($data, "cps={$data->cps} dup=$duplicates count={$this->samples->size()}");
		}
	}

}