<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class EsotericAlertsSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender instanceof Player) {
            if ($sender->hasPermission("ac.alerts")) {
                $playerData = Esoteric::getInstance()->dataManager->get($sender);
                $toggle = $args['toggle'] ?? !$playerData->hasAlerts;
                if (!is_bool($toggle)) {
                    if (in_array($toggle, ['on', 'off', 'enable', 'disable'])) {
                        $toggle = ["on" => true, "off" => false, "enable" => true, "disable" => false, "true" => true, "false" => false][$toggle];
                    } else {
                        $toggle = !$playerData->hasAlerts;
                    }
                }
                $playerData->hasAlerts = $toggle;
                $sender->sendMessage($playerData->hasAlerts ? TextFormat::GREEN . "Your alerts have been turned on" : TextFormat::RED . "Your alerts have been disabled");
            } else {
                $sender->sendMessage($this->getPermissionMessage());
            }
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new RawStringArgument("toggle", true));
    }
}





