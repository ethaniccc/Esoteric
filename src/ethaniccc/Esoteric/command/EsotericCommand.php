<?php

namespace ethaniccc\Esoteric\command;

use CortexPE\Commando\BaseCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericAlertsSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericBanwaveSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericDebugSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericDelaySubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericExemptSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericHelpSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericLogsSubCommand;
use ethaniccc\Esoteric\command\subcommands\EsotericTimingsSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class EsotericCommand extends BaseCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		$sender->sendMessage(TextFormat::GOLD . "Esoteric anti-cheat, created by ethaniccc.");
	}

	protected function prepare(): void {
		// the commands: help, logs, delay, alerts, banwave, timings, exempt, debug
		$this->setPermission('ac.command');
		$this->registerSubCommand(new EsotericHelpSubCommand($this->plugin, "help", "A help message with all the commands of the Esoteric"));
		$this->registerSubCommand(new EsotericLogsSubCommand($this->plugin, "logs", "Retrieve user anti-cheat logs"));
		$this->registerSubCommand(new EsotericDelaySubCommand($this->plugin, "delay", "Set your anti-cheat alert delay cooldown"));
		$this->registerSubCommand(new EsotericAlertsSubCommand($this->plugin, "alerts", "Toggle alerts in-game on/off"));
		$this->registerSubCommand(new EsotericBanwaveSubCommand($this->plugin, "banwave", "Handle Esoteric banwaves in-game"));
		$this->registerSubCommand(new EsotericTimingsSubCommand($this->plugin, "timings", "Measure Esoteric performance with timings"));
		$this->registerSubCommand(new EsotericExemptSubCommand($this->plugin, "exempt", "Handle Esoteric exemption settings"));
		$this->registerSubCommand(new EsotericDebugSubCommand($this->plugin, "debug", "Handle Esoteric exemption settings"));
	}

}



