<?php

namespace ethaniccc\Esoteric\check\misc\editionfaker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\types\DeviceOS;

class EditionFakerA extends Check {

	private $faking = false;

	public function __construct() {
		parent::__construct("EditionFaker", "A", "Checks if the player is spoofing their device information", false);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof LoginPacket) {
			$givenOS = $packet->clientData["DeviceOS"];
			// thank you gophertunnel (https://github.com/Sandertv/gophertunnel/blob/master/minecraft/protocol/login/data.go#L28-L34)
			switch ($givenOS) {
				case DeviceOS::WINDOWS_10:
					$expectedID = "896928775";
					break;
				case DeviceOS::ANDROID:
				case DeviceOS::IOS:
					//case DeviceOS::AMAZON:
					$expectedID = "1739947436";
					break;
				case DeviceOS::NINTENDO:
					$expectedID = "2047319603";
					break;
				default:
					$expectedID = null;
					break;
			}
			try {
				$data = $packet->chainData;
				$parts = explode(".", $data['chain'][2]);
				$jwt = json_decode(base64_decode($parts[1]), true);
				$givenID = $jwt['extraData']['titleId'];
			} catch (\Exception $e) {
				return;
			}
			if ($expectedID !== null && $expectedID !== $givenID) {
				$this->faking = true;
			}
		} elseif ($packet instanceof MovePlayerPacket && $data->loggedIn) {
			if ($this->faking) {
				$this->flag($data);
			}
		}
	}

}