<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class EsotericDebugSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender->hasPermission("ac.command.debug") && $sender instanceof Player) {
            $data = Esoteric::getInstance()->dataManager->get($sender);
            if ($data === null) {
                $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " Something went very wrong, rejoin the server.");
                return;
            }
            $targetName = $args['player'] ?? null;
            if ($targetName === null) {
                $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " You need to give a player to debug.");
                return;
            }
            $target = Esoteric::getInstance()->dataManager->getFromName($targetName);
            if ($target === null) {
                $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " Player not found.");
                return;
            }
            $handler = $args['handler'] ?? null;
            if ($handler === null) {
                $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " You must specify a handler to attach to (or specify 'remove' to remove debugging)");
                return;
            } elseif ($handler === "remove") {
                foreach ($target->debugHandlers as $debugHandler) {
                    $debugHandler->remove($data);
                }
            } else {
                foreach ($target->debugHandlers as $debugHandler) {
                    if (strtolower($debugHandler->getName()) === strtolower($handler)) {
                        $debugHandler->add($data);
                        return;
                    }
                }
                $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " The specified debug handler was not found");
            }
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new RawStringArgument("player", true));
        $this->registerArgument(1, new RawStringArgument("handler", true));
    }
}




