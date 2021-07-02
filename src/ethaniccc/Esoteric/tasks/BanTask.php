<?php

namespace ethaniccc\Esoteric\tasks;

use DateTime;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class BanTask extends Task {

	private Player $player;
	private string $reason;
	private ?DateTime $expiration;

	public function __construct(Player $player, string $reason, DateTime $expiration = null) {
		$this->player = $player;
		$this->reason = $reason;
		$this->expiration = $expiration;
	}

	public function onRun() : void {
		Server::getInstance()->getNameBans()->addBan($this->player->getName(), $this->reason, $this->expiration, 'Esoteric AC');
		$this->player->kick(str_replace(['{prefix}', '{code}', '{expires}'], [Esoteric::getInstance()->getSettings()->getPrefix(), $this->reason, $this->expiration !== null ? $this->expiration->format("m/d/y h:i A T") : 'Never'], Esoteric::getInstance()->getSettings()->getBanMessage()));
	}
}