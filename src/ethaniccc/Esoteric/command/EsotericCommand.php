<?php

namespace ethaniccc\Esoteric\command;

use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\TextFormat;

class EsotericCommand extends Command implements PluginIdentifiableCommand {

	public function __construct() {
		parent::__construct("ac", "Main command for the Esoteric anti-cheat", "/ac <sub_command>", ["anticheat"]);
		$this->setPermissionMessage(TextFormat::RED . "I'm sorry, but you don't have access to use this command. Contact an administrator if you think this is an error.");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		$subCommand = $args[0] ?? null;
		if ($subCommand === null) {
			$sender->sendMessage(TextFormat::GOLD . "Esoteric anti-cheat, created by ethaniccc.");
			return;
		}
		switch ($subCommand) {
			case "help":
				if ($sender->hasPermission("ac.command.help")) {
					$helpMessage = TextFormat::GRAY . str_repeat("-", 8) . " " . TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::YELLOW . "Eso" . TextFormat::GOLD . "teric" . TextFormat::GRAY . "] " . TextFormat::RESET . TextFormat::GRAY . str_repeat("-", 8) . PHP_EOL . TextFormat::YELLOW . "/ac logs <player> - Get the anti-cheat logs of the specified player (permission=ac.command.logs)" . PHP_EOL . TextFormat::GOLD . "/ac delay <delay> - Set your alert cooldown delay (permission=ac.command.delay)";
					$sender->sendMessage($helpMessage);
				} else {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "logs":
				if ($sender->hasPermission("ac.command.logs")) {
					$selectedUser = $args[1] ?? null;
					if ($selectedUser === null) {
						$sender->sendMessage(TextFormat::RED . "You need to specify a player.");
					} else {
						$data = Esoteric::getInstance()->dataManager->getFromName($selectedUser);
						if ($data === null) {
							// try the log cache
							$cached = Esoteric::getInstance()->logCache[strtolower($selectedUser)] ?? null;
							$sender->sendMessage($cached === null ? TextFormat::RED . "The specified player was not found." : $cached);
						} else {
							$message = null;
							foreach ($data->checks as $check) {
								$checkData = $check->getData();
								if ($checkData["violations"] >= 1) {
									if ($message === null) {
										$message = "";
									}
									$message .= TextFormat::YELLOW . $checkData["full_name"] . TextFormat::WHITE . " - " . $checkData["description"] . TextFormat::GRAY . " (" . TextFormat::RED . "x" . var_export(round($checkData["violations"], 3), true) . TextFormat::GRAY . ")" . PHP_EOL;
								}
							}
							$sender->sendMessage($message === null ? TextFormat::GREEN . "{$data->player->getName()} has no logs" : TextFormat::RED . TextFormat::BOLD . $data->player->getName() . "'s logs:\n" . TextFormat::RESET . $message);
						}
					}
				} else {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "delay":
				if ($sender->hasPermission("ac.command.delay") && $sender instanceof Player) {
					$delay = (int) ($args[1] ?? Esoteric::getInstance()->getSettings()->getAlertCooldown());
					$playerData = Esoteric::getInstance()->dataManager->get($sender);
					$playerData->alertCooldown = $delay;
					$sender->sendMessage(TextFormat::GREEN . "Your alert cooldown was set to $delay seconds");
				} elseif ($sender instanceof Player) {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "alerts":
				if ($sender->hasPermission("ac.alerts") && $sender instanceof Player) {
					$playerData = Esoteric::getInstance()->dataManager->get($sender);
					if (isset($args[1])) {
						switch ($args[1]) {
							case "on":
							case "true":
							case "enable":
								$alerts = true;
								break;
							case "off":
							case "false":
							case "disable":
								$alerts = false;
								break;
							default:
								$alerts = !$playerData->hasAlerts;
								break;
						}
					} else {
						$alerts = !$playerData->hasAlerts;
					}
					$playerData->hasAlerts = $alerts;
					$sender->sendMessage($playerData->hasAlerts ? TextFormat::GREEN . "Your alerts have been turned on" : TextFormat::RED . "Your alerts have been disabled");
				} elseif ($sender instanceof Player) {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
		}
	}

	public function getPlugin(): Plugin {
		return Esoteric::getInstance()->getPlugin();
	}

}