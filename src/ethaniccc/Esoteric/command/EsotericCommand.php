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
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use function count;
use function implode;
use function in_array;
use function is_null;
use function mt_rand;
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
		if (is_null($subCommand)) {
			$sender->sendMessage(TextFormat::GOLD . "Esoteric anti-cheat, created by ethaniccc.");
			return;
		}
		switch ($subCommand) {
			case "help":
				if ($sender->hasPermission("ac.command.help")) {
					$helpMessage = TextFormat::GRAY . str_repeat("-", 8) . " " . TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::YELLOW . "Eso" . TextFormat::GOLD . "teric" . TextFormat::GRAY . "] " . TextFormat::RESET . TextFormat::GRAY . str_repeat("-", 8) . PHP_EOL . TextFormat::YELLOW . "/ac logs <player> - Get the anti-cheat logs of the specified player (permission=ac.command.logs)" . PHP_EOL . TextFormat::GOLD . "/ac delay <delay> - Set your alert cooldown delay (permission=ac.command.delay)" . PHP_EOL . TextFormat::YELLOW . "/ac alerts <on/off> - Toggle alerts on or off (permission=ac.alerts)" . PHP_EOL . TextFormat::GOLD . "/ac banwave <subcommand> - Do actions with banwaves (permission=ac.command.banwave)" . PHP_EOL . TextFormat::YELLOW . "/ac timings <seconds> - Enable timings for a certain amount of seconds to see performance (permission=ac.command.timings)";
					$sender->sendMessage($helpMessage);
				} else {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "logs":
				if ($sender->hasPermission("ac.command.logs")) {
					$selectedUser = $args[1] ?? null;
					if (is_null($selectedUser)) {
						$sender->sendMessage(TextFormat::RED . 'You need to specify a player.');
					} else {
						$data = Esoteric::getInstance()->dataManager->getFromName($selectedUser);
						if (is_null($data)) {
							// try the log cache
							$cached = Esoteric::getInstance()->logCache[strtolower($selectedUser)] ?? null;
							$sender->sendMessage(is_null($cached) ? TextFormat::RED . 'The specified player was not found.' : $cached);
						} else {
							$message = null;
							foreach ($data->checks as $check) {
								$checkData = $check->getData();
								if ($checkData['violations'] >= 1) {
									if (is_null($message)) {
										$message = '';
									}
									$message .= TextFormat::YELLOW . $checkData['full_name'] . TextFormat::WHITE . ' - ' . $checkData['description'] . TextFormat::GRAY . ' (' . TextFormat::RED . 'x' . var_export(round($checkData['violations'], 3), true) . TextFormat::GRAY . ")" . PHP_EOL;
								}
							}
							$sender->sendMessage(is_null($message) ? TextFormat::GREEN . "{$data->player->getName()} has no logs" : TextFormat::RED . TextFormat::BOLD . $data->player->getName() . "'s logs:\n" . TextFormat::RESET . $message);
						}
					}
				} else {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "delay":
				if ($sender instanceof Player) {
					if ($sender->hasPermission("ac.command.delay")) {
						$delay = (int) ($args[1] ?? Esoteric::getInstance()->getSettings()->getAlertCooldown());
						$playerData = Esoteric::getInstance()->dataManager->get($sender);
						$playerData->alertCooldown = $delay;
						$sender->sendMessage(TextFormat::GREEN . "Your alert cooldown was set to $delay seconds");
					} else {
						$sender->sendMessage($this->getPermissionMessage());
					}
				}
				break;
			case "alerts":
				if ($sender instanceof Player) {
					if ($sender->hasPermission("ac.alerts")) {
						$playerData = Esoteric::getInstance()->dataManager->get($sender);
						if (isset($args[1])) {
							$alerts = match ($args[1]) {
								"on", "true", "enable" => true,
								"off", "false", "disable" => false,
								default => !$playerData->hasAlerts,
							};
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
				if ($sender->hasPermission("ac.command.banwave")) {
					if (is_null(Esoteric::getInstance()->getBanwave())) {
						$sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
						return;
					}
					$sub = $args[1] ?? null;
					if (is_null($sub)) {
						$sender->sendMessage(TextFormat::RED . "Available sub commands: execute, undo, add, remove");
					} else {
						switch ($sub) {
							case "execute":
								Esoteric::getInstance()->getBanwave()->execute();
								break;
							case "undo":
								$selected = $args[2] ?? null;
								if (is_null($selected)) {
									$sender->sendMessage(TextFormat::RED . "You need to specify a ban wave to undo");
									return;
								}
								$selected = (int) $selected;
								if (!in_array($selected, range(1, Esoteric::getInstance()->getBanwave()->getId()))) {
									$sender->sendMessage(TextFormat::RED . "Invalid ban wave. Current ban wave ID is " . Esoteric::getInstance()->getBanwave()->getId());
									return;
								}
								Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask(Esoteric::getInstance()->getDataFolder() . "banwaves/banwave-$selected.json", static function (Banwave $banwave) use ($sender): void {
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
								if (is_null($selected)) {
									$sender->sendMessage(TextFormat::RED . "You need to specify a player to remove from the ban wave");
									return;
								}
								Esoteric::getInstance()->getBanwave()->removeFromList($selected);
								$sender->sendMessage(TextFormat::GREEN . $selected . " was removed from the ban wave");
								break;
							case "add":
								$selected = $args[2] ?? null;
								if (is_null($selected)) {
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
				if ($sender->hasPermission("ac.command.timings")) {
					$time = (int) ($args[1] ?? 60);
					Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings on");
					Esoteric::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function (): void {
						Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings paste");
						Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings off");
					}), $time * 20);
				} else {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "exempt":
				if ($sender->hasPermission("ac.command.exempt")) {
					$sub = $args[1] ?? null;
					if (is_null($sub)) {
						$sender->sendMessage(TextFormat::RED . "Available sub commands: all, get, add");
						return;
					}
					switch ($sub) {
						case "all":
							$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . " People that are exempted from Esoteric: " . implode(", ", Esoteric::getInstance()->exemptList));
							break;
						case "get":
							$selected = $args[2] ?? null;
							if (is_null($selected)) {
								$sender->sendMessage(TextFormat::RED . "You need to specify a player to get the exempt status of");
								return;
							}
							if (($player = Server::getInstance()->getPlayer($selected)) !== null) {
								$selected = $player->getName();
							}
							$sender->sendMessage(in_array($selected, Esoteric::getInstance()->exemptList) ? TextFormat::GREEN . "$selected is exempt from Esoteric" : TextFormat::RED . "$selected is not exempt from Esoteric");
							break;
						case "add":
							$selected = $args[2] ?? null;
							if (is_null($selected)) {
								$sender->sendMessage(TextFormat::RED . "You need to specify a player to exempt");
								return;
							}
							if (($player = Server::getInstance()->getPlayer($selected)) !== null) {
								$selected = $player->getName();
							}
							Esoteric::getInstance()->exemptList[] = $selected;
							$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::GREEN . " $selected was exempted from Esoteric");
							break;
						case "remove":
							$selected = $args[2] ?? null;
							if (is_null($selected)) {
								$sender->sendMessage(TextFormat::RED . "You need to specify a player to un-exempt");
								return;
							}
							if (($player = Server::getInstance()->getPlayer($selected)) !== null) {
								$selected = $player->getName();
								$rand = mt_rand(1, 50);
								Esoteric::getInstance()->getScheduler()->scheduleTask(new KickTask($player, "Error processing packet (0x$rand) - rejoin the server"));
							}
							foreach (Esoteric::getInstance()->exemptList as $k => $n) {
								if (strtolower($n) === strtolower($selected)) {
									unset(Esoteric::getInstance()->exemptList[$k]);
									break;
								}
							}
							$sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " $selected was un-exempted from Esoteric");
							break;
					}
				} else {
					$sender->sendMessage($this->getPermissionMessage());
				}
				break;
			case "test":
				break;
		}
	}

}