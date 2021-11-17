<?php

namespace ethaniccc\Esoteric\tasks;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class KickTask extends Task{

	/** @var Player */
	private Player $player;
	/** @var string */
	private string $reason;

	public function __construct(Player $player, string $reason){
		$this->player = $player;
		$this->reason = $reason;
	}

	public function onRun() : void{
		$this->player->kick($this->reason, $this->reason);
	}

}