<?php

namespace ethaniccc\Esoteric\tasks;

use pocketmine\Player;
use pocketmine\scheduler\Task;

class KickTask extends Task {

	private Player $player;
	private string $reason;

	public function __construct(Player $player, string $reason) {
		$this->player = $player;
		$this->reason = $reason;
	}

	public function onRun(int $currentTick) {
		$this->player->kick($this->reason, false);
	}
}