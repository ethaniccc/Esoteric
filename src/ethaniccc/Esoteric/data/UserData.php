<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\data\movement\MovementCapture;
use ethaniccc\Esoteric\handler\InboundHandler;
use ethaniccc\Esoteric\handler\OutboundHandler;
use pocketmine\Player;

final class UserData {

	/** @var Player */
	public $player;
	/** @var string */
	public $identifier;

	/** @var MovementCapture */
	public $currentMovement;
	/** @var MovementCapture */
	public $lastMovement;

	/** @var InboundHandler */
	public $inboundHandler;
	/** @var OutboundHandler */
	public $outboundHandler;

	public function __construct(Player $player) {
		$this->player = $player;
		$this->identifier = "{$player->getAddress()} {$player->getPort()}";

		$this->currentMovement = MovementCapture::dummy();
		$this->lastMovement = MovementCapture::dummy();

		$this->inboundHandler = new InboundHandler($this);
		$this->outboundHandler = new OutboundHandler($this);
	}

	public function destroy(): void {
		$this->player = null;
		$this->inboundHandler->destroy();
		$this->inboundHandler = null;
	}

}