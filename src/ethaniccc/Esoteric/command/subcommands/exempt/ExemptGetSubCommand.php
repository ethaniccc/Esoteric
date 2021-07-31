<?php

namespace ethaniccc\Esoteric\command\subcommands\exempt;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ExemptGetSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender->hasPermission("ac.command.exempt")) {
            $selected = $args['player'] ?? null;
            if ($selected === null) {
                $sender->sendMessage(TextFormat::RED . "You need to specify a player to get the exempt status of");
                return;
            }
            if (($player = Server::getInstance()->getPlayer($selected)) !== null) {
                $selected = $player->getName();
            }
            $sender->sendMessage(in_array($selected, Esoteric::getInstance()->exemptList, true) ? TextFormat::GREEN . "$selected is exempt from Esoteric" : TextFormat::RED . "$selected is not exempt from Esoteric");
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new RawStringArgument("player", true));
    }
}






