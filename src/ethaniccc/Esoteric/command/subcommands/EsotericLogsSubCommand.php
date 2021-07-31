<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class EsotericLogsSubCommand extends BaseSubCommand {
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender->hasPermission("ac.command.logs")) {
            $selectedUser = $args['player'] ?? null;
            if ($selectedUser === null) {
                $sender->sendMessage(TextFormat::RED . "You need to specify a player.");
            } else {
                $data = Esoteric::getInstance()->dataManager->getFromName($selectedUser);
                if ($data === null) {
                    // try the log cache
                    $cached = Esoteric::getInstance()->logCache[strtolower($selectedUser)] ?? null;
                    $sender->sendMessage($cached === null ? TextFormat::RED . "The specified player was not found." : $cached);
                } else {
                    $message = null;
                    foreach ($data->checks as $check) {
                        $checkData = $check->getData();
                        if ($checkData["violations"] >= 1) {
                            if ($message === null) {
                                $message = "";
                            }
                            $message .= TextFormat::YELLOW . $checkData["full_name"] . TextFormat::WHITE . " - " . $checkData["description"] . TextFormat::GRAY . " (" . TextFormat::RED . "x" . var_export(round($checkData["violations"], 3), true) . TextFormat::GRAY . ")" . PHP_EOL;
                        }
                    }
                    $sender->sendMessage($message === null ? TextFormat::GREEN . "{$data->player->getName()} has no logs" : TextFormat::RED . TextFormat::BOLD . $data->player->getName() . "'s logs:\n" . TextFormat::RESET . $message);
                }
            }
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new RawStringArgument("player", true));
    }
}





