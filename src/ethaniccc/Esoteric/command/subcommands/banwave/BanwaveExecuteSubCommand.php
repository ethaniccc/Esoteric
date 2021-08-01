<?php

namespace ethaniccc\Esoteric\command\subcommands\banwave;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class BanwaveExecuteSubCommand extends BaseSubCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		if (Esoteric::getInstance()->getBanwave() === null) {
			$sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
			return;
		}

		Esoteric::getInstance()->getBanwave()->execute();
	}

	protected function prepare(): void {
		$this->setPermission('ac.command.banwave.execute');
	}
}







