<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\command\subcommands\banwave\BanwaveAddSubCommand;
use ethaniccc\Esoteric\command\subcommands\banwave\BanwaveExecuteSubCommand;
use ethaniccc\Esoteric\command\subcommands\banwave\BanwaveRemoveSubCommand;
use ethaniccc\Esoteric\command\subcommands\banwave\BanwaveUndoSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class EsotericBanwaveSubCommand extends BaseSubCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		if (Esoteric::getInstance()->getBanwave() === null) {
			$sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
			return;
		}

		$sender->sendMessage(TextFormat::RED . "Available sub commands: execute, undo, add, remove");
	}

	protected function prepare(): void {
		$this->setPermission('ac.command.banwave');
		$this->registerSubCommand(new BanwaveAddSubCommand("add", "Create a new banwave in the Esoteric anti-cheat"));
		$this->registerSubCommand(new BanwaveRemoveSubCommand("remove", "Remove an existing Esoteric banwave"));
		$this->registerSubCommand(new BanwaveUndoSubCommand("undo", "Undo the punishments executed from a specified banwave"));
		$this->registerSubCommand(new BanwaveExecuteSubCommand("execute", "Execute a banwave in the Esoteric anti-cheat"));
	}

}





