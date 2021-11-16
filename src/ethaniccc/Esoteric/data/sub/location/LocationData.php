<?php

namespace ethaniccc\Esoteric\data\sub\location;

use pocketmine\world\Position;

final class LocationData{

	/** @var int */
	public int $entityRuntimeId;
	/** @var bool */
	public bool $isPlayer;
	/** @var Position */
	public Position $currentLocation;
	/** @var Position */
	public Position $lastLocation;
	/** @var Position */
	public Position $receivedLocation;
	/** @var int */
	public int $newPosRotationIncrements = 0;
	/** @var int */
	public int $isSynced = 0;
	/** @var float */
	public float $hitboxHeight = 1.8;
	public float $hitboxWidth = 0.3;

	public function __construct(int $entityRuntimeId, bool $isPlayer, Position $location, float $hitboxWidth, float $hitboxHeight){
		$this->entityRuntimeId = $entityRuntimeId;
		$this->isPlayer = $isPlayer;
		$this->currentLocation = $location;
		$this->lastLocation = $location;
		$this->receivedLocation = $location;
		$this->hitboxWidth = $hitboxWidth;
		$this->hitboxHeight = $hitboxHeight;
	}

}