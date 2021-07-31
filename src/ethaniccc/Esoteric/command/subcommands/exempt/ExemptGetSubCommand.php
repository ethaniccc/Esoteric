<?php

namespace ethaniccc\Esoteric\command\subcommands\exempt;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\args\TargetArgument;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class ExemptGetSubCommand extends BaseSubCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		$selected = $args['player']->getName();
		$sender->sendMessage(in_array($selected, Esoteric::getInstance()->exemptList, true) ? TextFormat::GREEN . "$selected is exempt from Esoteric" : TextFormat::RED . "$selected is not exempt from Esoteric");
	}

	protected function prepare(): void {
		$this->setPermission('ac.command.exempt.get');
		$this->registerArgument(0, new TargetArgument("player"));
	}
}






