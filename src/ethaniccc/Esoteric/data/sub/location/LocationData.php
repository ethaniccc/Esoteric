<?php

namespace ethaniccc\Esoteric\data\sub\location;

use ethaniccc\Esoteric\utils\EvictingList;
use pocketmine\level\Position;

final class LocationData {

	/** @var int */
	public $entityRuntimeId;
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
	/** @var EvictingList */
	public $history;
	/** @var float */
	public $hitboxWidth = 0.3, $hitboxHeight = 1.8;

}