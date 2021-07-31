<?php

namespace ethaniccc\Esoteric\command\subcommands\banwave;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class BanwaveRemoveSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if ($sender->hasPermission("ac.command.banwave")) {
            if (Esoteric::getInstance()->getBanwave() === null) {
                $sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
                return;
            }

            $selected = $args['player'] ?? null;
            if ($selected === null) {
                $sender->sendMessage(TextFormat::RED . "You need to specify a player to remove from the ban wave");
                return;
            }
            Esoteric::getInstance()->getBanwave()->removeFromList($selected);
            $sender->sendMessage(TextFormat::GREEN . $selected . " was removed from the ban wave");
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new RawStringArgument("player", true));
    }
}





