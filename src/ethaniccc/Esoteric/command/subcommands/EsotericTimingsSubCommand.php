<?php

namespace ethaniccc\Esoteric\command\subcommands;

use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

class EsotericTimingsSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if ($sender->hasPermission("ac.command.timings")) {
            $time = (int) ($args['time'] ?? 60);
            Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings on");
            Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function (): void {
                Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings paste");
                Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "timings off");
            }), $time * 20);
        } else {
            $sender->sendMessage($this->getPermissionMessage());
        }
    }

    protected function prepare(): void {
        $this->registerArgument(0, new IntegerArgument("time", true));
    }
}





