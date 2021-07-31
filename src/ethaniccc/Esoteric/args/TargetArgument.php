<?php

namespace ethaniccc\Esoteric\args;

use CortexPE\Commando\args\BaseArgument;
use pocketmine\command\CommandSender;
use pocketmine\IPlayer;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\Player;
use pocketmine\Server;

class TargetArgument extends BaseArgument {
	public function getNetworkType(): int {
		return AvailableCommandsPacket::ARG_TYPE_TARGET;
	}

	public function getTypeName(): string {
		return "target";
	}

	public function canParse(string $testString, CommandSender $sender): bool {
		return Player::isValidUserName($testString);
	}

	public function parse(string $argument, CommandSender $sender): IPlayer {
		return Server::getInstance()->getOfflinePlayer($argument);
	}
}