<?php

namespace ethaniccc\Esoteric\handlers;

use ethaniccc\Esoteric\data\Data;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\thread\EsotericThread;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\world\World;
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

			$data->currentChunkHash = World::chunkHash(((int)$packet->getPosition()->x) >> 4, ((int)$packet->getPosition()->z) >> 4);

			$data->lastPosition = clone $data->currentPosition;
			$data->currentPosition = $packet->getPosition()->subtract(0, 1.62, 0);
			$data->lastMoveDelta = clone $data->currentMoveDelta;
			$data->currentMoveDelta = $data->currentPosition->subtractVector($data->lastPosition);

			if ($data->loggedIn) {
				$data->queueDebugMessageSend("the block under you is " . $data->world->getBlock($data->currentPosition->subtract(0, 1, 0))->getName());
			}

			++$data->currentTick;
		} elseif ($packet instanceof InventoryTransactionPacket) {
			$trData = $packet->trData;
			if ($trData instanceof UseItemOnEntityTransactionData && $trData->getActionType() === UseItemOnEntityTransactionData::ACTION_ATTACK) {
				$this->click($data);
			}
		} elseif ($packet instanceof LevelSoundEventPacket && $packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE) {
			$this->click($data);
		} elseif ($packet instanceof SetLocalPlayerAsInitializedPacket) {
			$data->loggedIn = true;
		} elseif ($packet instanceof NetworkStackLatencyPacket) {
			EsotericThread::getInstance()->networkStackLatencyHandler->execute($data->identifier, $packet->timestamp);
		}
	}

	private function click(Data $data): void {
		$this->clicks[] = $data->currentTick;
		if (count($data->clickSamples) === 20) {
			// messy way to get rid of all the samples
			while($data->clickSamples->shift() !== null){}
		}
		$data->clickSamples[] = $data->currentTick - $this->lastClickTick;
		foreach ($this->clicks as $key => $tick) {
			if ($data->currentTick - $tick > 20) {
				unset($this->clicks[$key]);
			}
		}
		$data->clicksPerSecond = count($this->clicks);
		$data->queueDebugMessageSend("cps={$data->clicksPerSecond}");
	}

}