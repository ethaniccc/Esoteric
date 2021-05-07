<?php

namespace ethaniccc\Esoteric\command;

use ethaniccc\Esoteric\Constants;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\AsyncClosureTask;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use ethaniccc\Esoteric\utils\banwave\Banwave;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\lang\Language;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function count;
use function implode;
use function in_array;
use function range;
use function round;
use function serialize;
use function str_repeat;
use function strtolower;
use function unserialize;
use function var_export;
use const PHP_EOL;

class EsotericCommand extends Command {

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
				$helpMessage = TextFormat::GRAY . str_repeat("-", 8) . " " . TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::YELLOW . "Eso" . TextFormat::GOLD . "teric" . TextFormat::GRAY . "] " . TextFormat::RESET . TextFormat::GRAY . str_repeat("-", 8) . PHP_EOL . TextFormat::YELLOW . "/ac logs <player> - Get the anti-cheat logs of the specified player (permission=ac.command.logs)" . PHP_EOL . TextFormat::GOLD . "/ac delay <delay> - Set your alert cooldown delay (permission=ac.command.delay)" . PHP_EOL . TextFormat::YELLOW . "/ac alerts <on/off> - Toggle alerts on or off (permission=ac.alerts)" . PHP_EOL . TextFormat::GOLD . "/ac banwave <subcommand> - Do actions with banwaves (permission=ac.command.banwave)" . PHP_EOL . TextFormat::YELLOW . "/ac timings <seconds> - Enable timings for a certain amount of seconds to see performance (permission=ac.command.timings)";
				$sender->sendMessage($helpMessage);
				break;
			case "logs":
				if ($sender->hasPermission(Constants::LOGS_PERMISSION)) {
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
				if ($sender instanceof Player) {
					if ($sender->hasPermission(Constants::ALERT_PERMISSION)) {
						$delay = (int) ($args[1] ?? Esoteric::getInstance()->getSettings()->getAlertCooldown());
						$playerData = Esoteric::getInstance()->dataManager->get($sender->getNetworkSession());
						$playerData->alertCooldown = $delay;
						$sender->sendMessage(TextFormat::GREEN . "Your alert cooldown was set to $delay seconds");
					} else {
						$sender->sendMessage($this->getPermissionMessage());
					}
				}
				break;
			case "alerts":
				if ($sender instanceof Player) {
					if ($sender->hasPermission(Constants::ALERT_PERMISSION)) {
						$playerData = Esoteric::getInstance()->dataManager->get($sender->getNetworkSession());
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
					} else {
						$sender->sendMessage($this->getPermissionMessage());
					}
				}
				break;
			case "banwave":
				if ($sender->hasPermission(Constants::BANWAVE_PERMISSION)) {
					if (Esoteric::getInstance()->getBanwave() === null) {
						$sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
						return;
					}
					$sub = $args[1] ?? null;
					if ($sub === null) {
						$sender->sendMessage(TextFormat::RED . "Available sub commands: execute, undo, add, remove");
					} else {
						switch ($sub) {
							case "execute":
								Esoteric::getInstance()->getBanwave()->execute();
								break;
							case "undo":
								$selected = $args[2] ?? null;
								if ($selected === null) {
									$sender->sendMessage(TextFormat::RED . "You need to specify a ban wave to undo");
									return;
								}
								$selected = (int) $selected;
								if (!in_array($selected, range(1, Esoteric::getInstance()->getBanwave()->getId()))) {
									$sender->sendMessage(TextFormat::RED . "Invalid ban wave. Current ban wave ID is " . Esoteric::getInstance()->getBanwave()->getId());
									return;
								}
								Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask(Esoteric::getInstance()->getPlugin()->getDataFolder() . "banwaves/banwave-$selected.json", static function (Banwave $banwave) use ($sender): void {
									if (count($banwave->getBannedPlayers()) === 0) {
										$sender->sendMessage(TextFormat::RED . "No banned players found in this ban wave");
									} else {
										$players = [];
										foreach ($banwave->getBannedPlayers() as $bannedPlayer) {
											$players[] = $bannedPlayer;
											Server::getInstance()->getNameBans()->remove($bannedPlayer);
											$banwave->removeFromBanned($bannedPlayer);
										}
										$sender->sendMessage(TextFormat::GREEN . "Players unbanned: " . implode(", ", $players));
										$banwave = serialize($banwave);
										Server::getInstance()->getAsyncPool()->submitTask(new AsyncClosureTask(static function () use ($banwave): void {
											$banwave = unserialize($banwave);
											/** @var Banwave $banwave */
											$banwave->update();
										}));
									}
								}));
								break;
							case "remove":
								$selected = $args[2] ?? null;
								if ($selected === null) {
									$sender->sendMessage(TextFormat::RED . "You need to specify a player to remove from the ban wave");
									return;
								}
								Esoteric::getInstance()->getBanwave()->removeFromList($selected);
								$sender->sendMessage(TextFormat::GREEN . $selected . " was removed from the ban wave");
								break;
							case "add":
								$selected = $args[2] ?? null;
								if ($selected === null) {
									$sender->sendMessage(TextFormat::RED . "You need to specify a player to add to the ban wave");
									return;
								}
								Esoteric::getInstance()->getBanwave()->add($selected, "manual");
								$sender->sendMessage(TextFormat::GREEN . $selected . " was added to the ban wave");
								break;
						}
					}
				} else {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "timings":
				if ($sender->hasPermission(Constants::TIMINGS_PERMISSION)) {
					$time = (int) ($args[1] ?? 60);
					$s = new ConsoleCommandSender($sender->getServer(), new Language("eng"));
					Server::getInstance()->dispatchCommand($s, "timings on");
					Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function () use ($s): void {
						Server::getInstance()->dispatchCommand($s, "timings paste");
						Server::getInstance()->dispatchCommand($s, "timings off");
					}), $time * 20);
				} else {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "test":
				if ($sender->hasPermission(DefaultPermissions::ROOT_OPERATOR) && $sender instanceof Player) {
				}
				break;
		}
	}

	public function getPlugin(): Plugin {
		return Esoteric::getInstance()->getPlugin();
	}

}