<?php

namespace ethaniccc\Esoteric\check\misc\editionfaker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use Exception;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\utils\TextFormat;
use function base64_decode;
use function explode;
use function json_decode;

class EditionFakerA extends Check {

	public function __construct() {
		parent::__construct("EditionFaker", "A", "Checks if the player is spoofing their device information", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof LoginPacket) {
			try {
				$d = $packet->chainData;
				$parts = explode(".", $d['chain'][2]);
				$jwt = json_decode(base64_decode($parts[1]), true);
				$titleID = $jwt['extraData']['titleId'];
			} catch (Exception $e) {
				return;
			}
			$givenOS = $packet->clientData["DeviceOS"];
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
					Esoteric::getInstance()->loggerThread->write("Unknown TitleID from " . TextFormat::clean($packet->username) . " (titleID=$titleID os=$givenOS)");
					return;
			}
			if ($givenOS !== $expectedOS) {
				$this->flag($data, ["titleID" => $titleID, "givenOS" => $givenOS, "expectedOS" => $expectedOS]);
			}
		}
	}

}