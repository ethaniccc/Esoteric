<?php

namespace ethaniccc\Esoteric\command\subcommands\exempt;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\args\TargetArgument;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class ExemptAddSubCommand extends BaseSubCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		$selected = $args['player']->getName();
		Esoteric::getInstance()->exemptList[] = $selected;
		$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::GREEN . " $selected was exempted from Esoteric");
	}

	protected function prepare(): void {
		$this->setPermission('ac.command.exempt.add');
		$this->registerArgument(0, new TargetArgument("player"));
	}
}







