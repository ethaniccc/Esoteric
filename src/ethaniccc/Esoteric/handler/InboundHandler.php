<?php

namespace ethaniccc\Esoteric\handler;

use ethaniccc\Esoteric\data\UserData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

final class InboundHandler {

	private $data;

	public function __construct(UserData $data) {
		$this->data = $data;
	}

	public function execute(DataPacket $packet): void {
		$data = $this->data;
		if ($packet instanceof PlayerAuthInputPacket) {
			$data->player->sendMessage("YOU TICKED OMG W TF/F/?~!?!?!?");
		}
	}

	public function destroy(): void {
		$this->data = null;
	}

}