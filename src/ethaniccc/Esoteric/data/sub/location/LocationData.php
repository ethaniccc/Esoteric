<?php

namespace ethaniccc\Esoteric\data\sub\location;

use pocketmine\world\Position;

final class LocationData {

	/** @var int */
	public $entityRuntimeId;
	/** @var bool */
	public $isPlayer;
	/** @var Position */
	public $currentLocation;
	/** @var Position */
	public $lastLocation;
	/** @var Position */
	public $receivedLocation;
	/** @var int */
	public $newPosRotationIncrements = 0;
	/** @var int */
	public $isSynced = 0;
	/** @var float */
	public $hitboxWidth = 0.3, $hitboxHeight = 1.8;

	public function __construct(int $entityRuntimeId, bool $isPlayer, Position $location, float $hitboxWidth, float $hitboxHeight) {
		$this->entityRuntimeId = $entityRuntimeId;
		$this->isPlayer = $isPlayer;
		$this->currentLocation = $location;
		$this->lastLocation = $location;
		$this->receivedLocation = $location;
		$this->hitboxWidth = $hitboxWidth;
		$this->hitboxHeight = $hitboxHeight;
	}

}