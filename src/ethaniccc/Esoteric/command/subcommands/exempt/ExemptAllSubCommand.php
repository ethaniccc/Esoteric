<?php

namespace ethaniccc\Esoteric\command\subcommands\exempt;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;

class ExemptAllSubCommand extends BaseSubCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . " People that are exempted from Esoteric: " . implode(", ", Esoteric::getInstance()->exemptList));
	}

	protected function prepare(): void {
		$this->setPermission('ac.command.exempt.all');
	}
}






