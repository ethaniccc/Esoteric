<?php

namespace ethaniccc\Esoteric\tasks;

use DateTime;
use ethaniccc\Esoteric\Constants;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;

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

	public function onRun(): void {
		Server::getInstance()->getNameBans()->addBan($this->player->getName(), $this->reason, $this->expiration, Constants::PUNISHMENT_ENTRY_NAME);
		$this->player->kick($this->reason, $this->reason);
	}
}