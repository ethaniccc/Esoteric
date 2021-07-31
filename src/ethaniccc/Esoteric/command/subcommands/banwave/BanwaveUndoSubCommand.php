<?php

namespace ethaniccc\Esoteric\command\subcommands\banwave;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\AsyncClosureTask;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use ethaniccc\Esoteric\utils\banwave\Banwave;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class BanwaveUndoSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if ($sender->hasPermission("ac.command.banwave")) {
            if (Esoteric::getInstance()->getBanwave() === null) {
                $sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
                return;
            }

            $selected = $args['id'] ?? null;
            if ($selected === null) {
                $sender->sendMessage(TextFormat::RED . "You need to specify a ban wave to undo");
                return;
            }
            $selected = (int)$selected;
            if (!in_array($selected, range(1, Esoteric::getInstance()->getBanwave()->getId()), true)) {
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
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new RawStringArgument("id", true));
    }
}







