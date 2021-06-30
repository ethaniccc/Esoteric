<?php

namespace ethaniccc\Esoteric\data\sub\location;

use ethaniccc\Esoteric\utils\EvictingList;
use pocketmine\math\Vector3;

final class LocationData {

	public int $entityRuntimeId;

	public Vector3 $currentLocation;
	public Vector3 $lastLocation;
	public Vector3 $receivedLocation;

	public int $newPosRotationIncrements = 0;
	public int $isSynced = 0;

	public EvictingList $history;

	public float $locationOffset = 0.0;
	public float $hitboxWidth = 0.3;
	public float $hitboxHeight = 1.8;

	public bool $isHuman = false;

}