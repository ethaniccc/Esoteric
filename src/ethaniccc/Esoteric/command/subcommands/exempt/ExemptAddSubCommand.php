<?php

namespace ethaniccc\Esoteric\command\subcommands\exempt;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ExemptAddSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender->hasPermission("ac.command.exempt")) {
            $selected = $args['player'] ?? null;
            if ($selected === null) {
                $sender->sendMessage(TextFormat::RED . "You need to specify a player to exempt");
                return;
            }
            if (($player = Server::getInstance()->getPlayer($selected)) !== null) {
                $selected = $player->getName();
            }
            Esoteric::getInstance()->exemptList[] = $selected;
            $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::GREEN . " $selected was exempted from Esoteric");
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new RawStringArgument("player", true));
    }
}







