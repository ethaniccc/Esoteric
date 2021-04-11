<?php

namespace ethaniccc\Esoteric\tasks;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class BanTask extends Task {

	/** @var Player */
	private $player;
	/** @var string */
	private $reason;

	public function __construct(Player $player, string $reason) {
		$this->player = $player;
		$this->reason = $reason;
	}

	public function onRun(int $currentTick) {
		Server::getInstance()->getNameBans()->addBan($this->player->getName(), $this->reason, null, "Esoteric AC");
		$this->player->kick($this->reason, false);
	}
}