<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class EsotericDelaySubCommand extends BaseSubCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		$delay = $args['delay'] ?? Esoteric::getInstance()->getSettings()->getAlertCooldown();
		$playerData = Esoteric::getInstance()->dataManager->get($sender);
		$playerData->alertCooldown = $delay;
		$sender->sendMessage(TextFormat::GREEN . "Your alert cooldown was set to $delay seconds");
	}

	protected function prepare(): void {
		$this->setPermission('ac.command.delay');
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new IntegerArgument("delay", true));
	}
}





