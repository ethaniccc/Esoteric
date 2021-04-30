<?php

namespace ethaniccc\Esoteric\tasks;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use DateTime;

class BanTask extends Task {

	/** @var Player */
	private $player;
	/** @var string */
	private $reason;
	/** @var DateTime|null */
	private $expiration;

	public function __construct(Player $player, string $reason, DateTime $expiration = null) {
		$this->player = $player;
		$this->reason = $reason;
		$this->expiration = $expiration;
	}

	public function onRun(int $currentTick) {
		Server::getInstance()->getNameBans()->addBan($this->player->getName(), $this->reason, $this->expiration, "Esoteric AC");
		$this->player->kick($this->reason, false);
	}
}