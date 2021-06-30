<?php

namespace ethaniccc\Esoteric\tasks;

use pocketmine\Player;
use pocketmine\scheduler\Task;

class KickTask extends Task {

	/** @var Player */
	private $player;
	/** @var string */
	private $reason;

	public function __construct(Player $player, string $reason) {
		$this->player = $player;
		$this->reason = $reason;
	}

	public function onRun(int $currentTick) {
		$this->player->kick($this->reason, false);
	}
}