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
			$givenOS = $data->playerOS;
			switch ($titleID) {
				case "896928775":
					$expectedOS = DeviceOS::WINDOWS_10;
					break;
				case "2047319603":
					$expectedOS = DeviceOS::NINTENDO;
					break;
				case "1739947436":
					$expectedOS = DeviceOS::ANDROID;
					break;
				case "2044456598":
					$expectedOS = DeviceOS::PLAYSTATION;
					break;
				case "1828326430":
					$expectedOS = DeviceOS::XBOX;
					break;
				case "1810924247":
					$expectedOS = DeviceOS::IOS;
					break;
				default:
					Esoteric::getInstance()->logger->write("Unknown TitleID from " . TextFormat::clean($authData->displayName) . " (titleID=$titleID os=$givenOS)");
					return;
			}
			if ($expectedOS !== $givenOS) {
				$this->flag($data, ["titleID" => $titleID, "givenOS" => $givenOS, "expectedOS" => $expectedOS]);
			}
		}
	}

}