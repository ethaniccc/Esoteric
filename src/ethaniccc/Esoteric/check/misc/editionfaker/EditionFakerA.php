<?php

namespace ethaniccc\Esoteric\check\misc\editionfaker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\utils\TextFormat;
use function is_null;

class EditionFakerA extends Check {

	public function __construct() {
		parent::__construct("EditionFaker", "A", "Checks if the player is spoofing their device information", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof LoginPacket) {
			$authData = PacketUtils::fetchAuthData($packet->chainDataJwt);
			$titleID = $authData->titleId;
			$givenOS = $data->playerOS;
			$expectedOS = match($titleID){
				'896928775' => DeviceOS::WINDOWS_10,
				'2047319603' => DeviceOS::NINTENDO,
				'1739947436' => DeviceOS::ANDROID,
				'2044456598' => DeviceOS::PLAYSTATION,
				'1828326430' => DeviceOS::XBOX,
				'1810924247' => DeviceOS::IOS,
				default => null
			};
			if(is_null($expectedOS)){
				Esoteric::getInstance()->loggerThread->write("Unknown TitleID from " . TextFormat::clean($authData->displayName) . " (titleID=$titleID os=$givenOS)");
				return;
			}
			if ($givenOS !== $expectedOS) {
				$this->flag($data, ["titleID" => $titleID, "givenOS" => $givenOS, "expectedOS" => $expectedOS]);
			}
		}
	}

}