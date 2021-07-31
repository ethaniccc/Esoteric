<?php

namespace ethaniccc\Esoteric\command\subcommands\exempt;

use CortexPE\Commando\BaseSubCommand;
use ethaniccc\Esoteric\args\TargetArgument;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\KickTask;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ExemptRemoveSubCommand extends BaseSubCommand {

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $selected = $args['player'];
        if ($selected->isOnline()) {
            $rand = mt_rand(1, 50);
            Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleTask(new KickTask($selected, "Error processing packet (0x$rand) - rejoin the server"));
        }
        $key = array_search(strtolower($selected->getName()), Esoteric::getInstance()->exemptList, true);
        if($key !== false){
        	unset(Esoteric::getInstance()->exemptList[$key]);
        }
        $sender->sendMessage(Esoteric::getInstance()->getSettings()->getPrefix() . TextFormat::RED . " {$selected->getName()} was un-exempted from Esoteric");
    }

    protected function prepare(): void {
    	$this->setPermission('ac.command.exempt.remove');
        $this->registerArgument(0, new TargetArgument("player"));
    }
}








