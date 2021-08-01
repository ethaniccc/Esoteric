<?php

namespace ethaniccc\Esoteric\command\subcommands\banwave;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\AsyncClosureTask;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use ethaniccc\Esoteric\utils\banwave\Banwave;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class BanwaveUndoSubCommand extends BaseSubCommand {

	public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
		if (Esoteric::getInstance()->getBanwave() === null) {
			$sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
			return;
		}

		$selected = $args['id'];
		$currentId = Esoteric::getInstance()->getBanwave()->getId();
		if ($selected < 1 or $selected > $currentId) {
			$sender->sendMessage(TextFormat::RED . "Invalid ban wave. Current ban wave ID is $currentId");
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
	}

	protected function prepare(): void {
		$this->setPermission('ac.command.banwave.undo');
		$this->registerArgument(0, new IntegerArgument("id"));
	}
}







