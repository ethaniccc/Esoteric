<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\constraint\InGameRequiredConstraint;
use ethaniccc\Esoteric\args\TargetArgument;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class EsotericDebugSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $data = Esoteric::getInstance()->dataManager->get($sender);
        if ($data === null) {
            $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " Something went very wrong, rejoin the server.");
            return;
        }
        $target = Esoteric::getInstance()->dataManager->getFromName($args['player']->getName());
        if ($target === null) {
            $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " Player not found.");
            return;
        }
        $handler = $args['handler'];
        if ($handler === "remove") {
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
    }

    protected function prepare(): void {
    	$this->setPermission('ac.command.debug');
    	$this->addConstraint(new InGameRequiredConstraint($this));
        $this->registerArgument(0, new TargetArgument("player"));
        $this->registerArgument(1, new RawStringArgument("handler"));
    }
}




