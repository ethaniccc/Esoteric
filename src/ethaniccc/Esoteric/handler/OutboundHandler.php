<?php

namespace ethaniccc\Esoteric\handler;

use ethaniccc\Esoteric\data\UserData;
use pocketmine\network\mcpe\protocol\DataPacket;

final class OutboundHandler {

	private $data;

	public function __construct(UserData $data) {
		$this->data = $data;
	}

	public function execute(DataPacket $packet): void {
		$data = $this->data;
	}

	public function destroy(): void {
		$this->data = null;
	}

}