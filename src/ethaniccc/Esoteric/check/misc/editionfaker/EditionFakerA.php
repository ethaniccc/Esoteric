<?php

namespace ethaniccc\Esoteric\check\misc\editionfaker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\EvictingList;
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
			try {
				$data = $packet->chainData;
				$parts = explode(".", $data['chain'][2]);
				$jwt = json_decode(base64_decode($parts[1]), true);
				$titleID = $jwt['extraData']['titleId'];
			} catch (\Exception $e) {
				return;
			}
			$expectedOS = new EvictingList(5);
			$givenOS = $packet->clientData["DeviceOS"];
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
			}
			if($expectedOS->size() > 0) {
				$passed = false;
				$expectedOS->iterate(function(int $deviceOS) use(&$passed, $givenOS): void {
					if (!$passed && $deviceOS === $givenOS) {
						$passed = true;
					}
				});
				$this->faking = !$passed;
			}
		} elseif ($packet instanceof MovePlayerPacket && $data->loggedIn && $this->faking) {
			$this->flag($data);
		}
	}

}