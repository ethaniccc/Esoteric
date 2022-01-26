<?php

namespace ethaniccc\Esoteric\command;

use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\AsyncClosureTask;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use ethaniccc\Esoteric\tasks\KickTask;
use ethaniccc\Esoteric\utils\banwave\Banwave;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\PluginIdentifiableCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat;

class EsotericCommand extends Command implements PluginIdentifiableCommand {

	public function execute(CommandSender $sender, string $commandLabel, array $args) {
		$subcommand = array_shift($args);
		if (!$sender->hasPermission("ac.command.$subcommand")) {
			$sender->sendMessage(TextFormat::RED . "You don't have permission to execute this command");
			return;
		}
		switch ($subcommand) {
			case "help":
				$helpMessage = TextFormat::GRAY . str_repeat("-", 8) . " " . TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::YELLOW . "Eso" . TextFormat::GOLD . "teric" . TextFormat::GRAY . "] " . TextFormat::RESET . TextFormat::GRAY . str_repeat("-", 8) . PHP_EOL . TextFormat::YELLOW . "/ac logs <player> - Get the anti-cheat logs of the specified player (permission=ac.command.logs)" . PHP_EOL . TextFormat::GOLD . "/ac delay <delay> - Set your alert cooldown delay (permission=ac.command.delay)" . PHP_EOL . TextFormat::YELLOW . "/ac alerts <on/off> - Toggle alerts on or off (permission=ac.alerts)" . PHP_EOL . TextFormat::GOLD . "/ac banwave <subcommand> - Do actions with banwaves (permission=ac.command.banwave)" . PHP_EOL . TextFormat::YELLOW . "/ac timings <seconds> - Enable timings for a certain amount of seconds to see performance (permission=ac.command.timings)";
				$sender->sendMessage($helpMessage);
				break;
			case "alerts":
				if ($sender instanceof Player) {
					$data = Esoteric::getInstance()->dataManager->get($sender);
					$toggle = array_shift($args) ?? !$data->hasAlerts;
					$data->hasAlerts = (bool) $toggle;
					$sender->sendMessage($data->hasAlerts ?
						TextFormat::GREEN . "Alerts have been enabled" :
						TextFormat::RED . "Alerts have been disabled");
				}
				break;
			case "banwave":
				if (Esoteric::getInstance()->getBanwave() === null) {
					$sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
					return;
				}
				switch (($sub = array_shift($args))) {
					case "add":
					case "remove":
						$selected = array_shift($args);
						if ($selected === null) {
							$sender->sendMessage(TextFormat::RED . "You need to specify a player to add to the banwave");
							return;
						}
						$target = Server::getInstance()->getPlayer($selected);
						if ($target === null) {
							$sender->sendMessage(TextFormat::RED . "The specified player was not found on the server");
							return;
						}
						if ($sub === "add") {
							Esoteric::getInstance()->getBanwave()->add($target->getName(), count($args) === 0 ? "manual" : implode(" ", $args));
							$sender->sendMessage(TextFormat::GREEN . "{$target->getName()} was added to the banwave");
						} else {
							Esoteric::getInstance()->getBanwave()->removeFromList($target->getName());
							$sender->sendMessage(TextFormat::GREEN . "{$target->getName()} was removed from the banwave");
						}
						break;
					case "execute":
						Esoteric::getInstance()->getBanwave()->execute();
						break;
					case "undo":
						$id = array_shift($args);
						if ($id === null) {
							$sender->sendMessage(TextFormat::RED . "You need to specify a banwave ID");
							return;
						}
						$id = (int) $id;
						$currentId = Esoteric::getInstance()->getBanwave()->getId();
						if ($id < 1 || $id > $currentId) {
							$sender->sendMessage(TextFormat::RED . "An invalid banwave ID was provided - the current banwave ID is $currentId");
							return;
						}
						Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask(Esoteric::getInstance()->getPlugin()->getDataFolder() . "banwaves/banwave-$selected.json", static function (Banwave $banwave) use ($sender): void {
							if (count($banwave->getBannedPlayers()) === 0) {
								$sender->sendMessage(TextFormat::RED . "No banned players found in this ban wave");
								return;
							}

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
						}));
						break;
					default:
						$sender->sendMessage(TextFormat::RED . "Available sub commands: execute, undo, add, remove");
						break;
				}
				break;
			case "debug":
				if ($sender instanceof Player) {
					$data = Esoteric::getInstance()->dataManager->get($sender);
					if ($data === null) {
						throw new AssumptionFailedError("Sender's data was null when running debug sub-command");
					}
					$wanted = array_shift($args);
					if ($wanted === null) {
						$sender->sendMessage(TextFormat::RED . "You need a target to debug");
						return;
					}
					$wanted = Server::getInstance()->getPlayer($wanted);
					if ($wanted === null) {
						$sender->sendMessage(TextFormat::RED . "The specified player was not found on the server");
						return;
					}
					$wanted = Esoteric::getInstance()->dataManager->get($wanted);
					if ($wanted === null) {
						throw new AssumptionFailedError("Target data was null, although target player was not in debug command");
					}
					$sub = array_shift($args);
					if ($sub === null) {
						$sender->sendMessage(TextFormat::RED . "You need to specify a handler to attach to - you can also remove yourself from handlers using the argument 'remove'.");
						return;
					}
					if ($sub === "remove") {
						foreach ($wanted->debugHandlers as $debugHandler) {
							$debugHandler->remove($data);
						}
					} else {
						foreach ($wanted->debugHandlers as $debugHandler) {
							if (strtolower($debugHandler->getName()) === strtolower($sub)) {
								$debugHandler->add($data);
								return;
							}
						}
						$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " The specified debug handler was not found");
					}
				}
				break;
			case "delay":
				if ($sender instanceof Player) {
					$delay = (int) (array_shift($args) ?? Esoteric::getInstance()->getSettings()->getAlertCooldown());
					$data = Esoteric::getInstance()->dataManager->get($sender);
					if ($data === null) {
						throw new AssumptionFailedError("Command sender's data was null while running delay command");
					}
					$data->alertCooldown = $delay;
					$sender->sendMessage(TextFormat::GREEN . "Your alert cooldown was set to $delay seconds");
				}
				break;
			case "exempt":
				switch ($sub = array_shift($args)) {
					case "add":
					case "remove":
						$target = Server::getInstance()->getPlayer(($name = array_shift($args)) ?? "");
						if ($target !== null) {
							$selected = $target->getName();
							if ($sub === "add") {
								Esoteric::getInstance()->exemptList[] = $selected;
								$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::GREEN . " $selected was exempted");
							} else {
								$rand = mt_rand(1, 50);
								Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleTask(new KickTask($target, "Error processing packet (0x$rand) - rejoin the server"));
							}
						} else {
							if ($sub === "add") {
								$sender->sendMessage(TextFormat::RED . "Invalid target provided to exempt");
							} else {
								$key = array_search(strtolower($name), Esoteric::getInstance()->exemptList, true);
								if ($key !== false) {
									unset(Esoteric::getInstance()->exemptList[$key]);
								}
								$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " $name was removed the exemption list");
							}
						}
						break;
					case "get":
						$selected = array_shift($args);
						if ($selected === null) {
							$sender->sendMessage(TextFormat::RED . "You must specify a player to check exemption status");
							return;
						}
						$sender->sendMessage(in_array($selected, Esoteric::getInstance()->exemptList, true) ? TextFormat::GREEN . "$selected is exempt from Esoteric" : TextFormat::RED . "$selected is not exempt from Esoteric");
						break;
					case "all":
						$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . " People that are exempted from Esoteric: " . implode(", ", Esoteric::getInstance()->exemptList));
						break;
				}
				break;
			case "logs":
				$selected = array_shift($args);
				if ($selected === null) {
					$sender->sendMessage(TextFormat::RED . "You need to specify a player to obtain logs from");
					return;
				}
				$selected = Server::getInstance()->getPlayer($selected);
				if ($selected === null) {
					$cached = Esoteric::getInstance()->logCache[strtolower($selected)] ?? null;
					$sender->sendMessage($cached === null ? TextFormat::RED . "The specified player was not found." : $cached);
					return;
				}
				$data = Esoteric::getInstance()->dataManager->get($selected);
				if ($data === null) {
					throw new AssumptionFailedError("Obtaining data from an online player returned null in logs command");
				}
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
				$sender->sendMessage($message === null ? TextFormat::GREEN . "{$selected->getName()} has logs" : TextFormat::RED . TextFormat::BOLD . $selected->getName() . "'s logs:\n" . TextFormat::RESET . $message);
				break;
			case "timings":
				$time = (int) (array_shift($args) ?? 60);
				Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings on");
				Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function (): void {
					Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings paste");
					Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings off");
				}), $time * 20);
				break;
		}
	}

	public function getPlugin(): Plugin {
		return Esoteric::getInstance()->getPlugin();
	}

}



