<?php

namespace ethaniccc\Esoteric\handlers;

use ethaniccc\Esoteric\data\Data;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemTransactionData;
use Threaded;
use function count;

final class InboundHandler extends Threaded {

	private $clicks;
	private $lastClickTick = 0;

	public function __construct() {
		$this->clicks = new Threaded();
	}

	public function execute(ServerboundPacket $packet, Data $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			$data->lastPosition = clone $data->currentPosition;
			$data->currentPosition = $packet->getPosition()->subtract(0, 1.62, 0);
			$data->lastMoveDelta = clone $data->currentMoveDelta;
			$data->currentMoveDelta = $data->currentPosition->subtractVector($data->lastPosition);

			++$data->currentTick;
		} elseif ($packet instanceof InventoryTransactionPacket) {
			$trData = $packet->trData;
			if ($trData instanceof UseItemOnEntityTransactionData && $trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK) {
				$this->click($data);
			}
		}
	}

	private function click(Data $data): void {
		$this->clicks[] = $data->currentTick;
		if (count($data->clickSamples) === 20) {
			$data->clickSamples = new Threaded();
		}
		$data->clickSamples[] = $data->currentTick - $this->lastClickTick;
		foreach ($this->clicks as $key => $tick) {
			if ($data->currentTick - $tick > 20) {
				unset($this->clicks[$key]);
			}
		}
		$data->clicksPerSecond = count($this->clicks);
	}

}