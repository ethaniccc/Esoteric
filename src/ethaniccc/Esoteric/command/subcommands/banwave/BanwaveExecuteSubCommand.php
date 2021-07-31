<?php

namespace ethaniccc\Esoteric\command\subcommands\banwave;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class BanwaveExecuteSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if ($sender->hasPermission("ac.command.banwave")) {
            if (Esoteric::getInstance()->getBanwave() === null) {
                $sender->sendMessage(TextFormat::RED . "Banwaves are disabled");
                return;
            }

            Esoteric::getInstance()->getBanwave()->execute();
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
    }
}







