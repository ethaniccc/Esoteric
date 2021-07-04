<?php

namespace ethaniccc\Esoteric\data\sub\location;

use pocketmine\math\Vector3;

final class LocationData {

	public int $entityRuntimeId;

	public Vector3 $currentLocation;
	public Vector3 $lastLocation;
	public Vector3 $receivedLocation;

	public int $newPosRotationIncrements = 0;
	public int $isSynced = 0;

	public float $hitboxWidth = 0.3;
	public float $hitboxHeight = 1.8;

	public bool $isHuman = false;

	public function __construct(int $entityRuntimeId, bool $isHuman, Vector3 $location) {
		$this->entityRuntimeId = $entityRuntimeId;
		$this->isHuman = $isHuman;
		$this->currentLocation = $location;
		$this->lastLocation = $location;
		$this->receivedLocation = $location;
	}

}