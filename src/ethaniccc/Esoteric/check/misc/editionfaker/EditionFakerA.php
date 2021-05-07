<?php

namespace ethaniccc\Esoteric\check\misc\editionfaker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\EvictingList;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\utils\TextFormat;

class EditionFakerA extends Check {

	public function __construct() {
		parent::__construct("EditionFaker", "A", "Checks if the player is spoofing their device information", false);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data): void {
		if ($packet instanceof LoginPacket) {
			$authData = PacketUtils::fetchAuthData($packet->chainDataJwt);
			$titleID = $authData->titleId;
			$expectedOS = new EvictingList(5);
			$givenOS = $data->playerOS;
			switch ($titleID) {
				case "896928775":
					$expectedOS->add(DeviceOS::WINDOWS_10);
					break;
				case "2047319603":
					$expectedOS->add(DeviceOS::NINTENDO);
					break;
				case "1739947436":
					$expectedOS->add(DeviceOS::IOS);
					$expectedOS->add(DeviceOS::ANDROID);
					break;
				default:
					Esoteric::getInstance()->logger->write("Unknown TitleID from " . TextFormat::clean($authData->displayName) . " (titleID=$titleID os=$givenOS)");
					return;
			}
			if ($expectedOS->size() > 0) {
				$passed = false;
				$expectedOS->iterate(static function (int $deviceOS) use (&$passed, $givenOS): void {
					if (!$passed && $deviceOS === $givenOS) {
						$passed = true;
					}
				});
				if (!$passed)
					$this->flag($data);
			}
		}
	}

}