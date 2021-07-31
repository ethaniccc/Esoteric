<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\BaseArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class EsotericDelaySubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender instanceof Player) {
            if ($sender->hasPermission("ac.command.delay")) {
                $delay = $args['delay'] ?? Esoteric::getInstance()->getSettings()->getAlertCooldown();
                $playerData = Esoteric::getInstance()->dataManager->get($sender);
                $playerData->alertCooldown = $delay;
                $sender->sendMessage(TextFormat::GREEN . "Your alert cooldown was set to $delay seconds");
            } else {
                $sender->sendMessage($this->getPermissionMessage());
            }
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new IntegerArgument("delay", true));
    }
}





