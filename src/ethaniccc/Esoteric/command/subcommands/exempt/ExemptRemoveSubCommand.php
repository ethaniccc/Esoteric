<?php

namespace ethaniccc\Esoteric\command\subcommands\exempt;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\KickTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ExemptRemoveSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender->hasPermission("ac.command.exempt")) {
            $selected = $args['player'] ?? null;
            if ($selected === null) {
                $sender->sendMessage(TextFormat::RED . "You need to specify a player to un-exempt");
                return;
            }
            if (($player = Server::getInstance()->getPlayer($selected)) !== null) {
                $selected = $player->getName();
                $rand = mt_rand(1, 50);
                Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleTask(new KickTask($player, "Error processing packet (0x$rand) - rejoin the server"));
            }
            foreach (Esoteric::getInstance()->exemptList as $k => $n) {
                if (strtolower($n) === strtolower($selected)) {
                    unset(Esoteric::getInstance()->exemptList[$k]);
                    break;
                }
            }
            $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " $selected was un-exempted from Esoteric");
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new RawStringArgument("player", true));
    }
}








