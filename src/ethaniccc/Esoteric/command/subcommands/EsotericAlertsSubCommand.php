<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\BooleanArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class EsotericAlertsSubCommand extends BaseSubCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		$playerData = Esoteric::getInstance()->dataManager->get($sender);
		$toggle = $args['toggle'] ?? !$playerData->hasAlerts;
		$playerData->hasAlerts = $toggle;
		$sender->sendMessage($playerData->hasAlerts ? TextFormat::GREEN . "Your alerts have been turned on" : TextFormat::RED . "Your alerts have been disabled");
	}

	protected function prepare(): void {
		$this->setPermission('ac.alerts');
		$this->addConstraint(new InGameRequiredConstraint($this));
		$this->registerArgument(0, new BooleanArgument("toggle", true));
	}
}





