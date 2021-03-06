<?php

namespace ethaniccc\Esoteric\data\sub\location;

use ethaniccc\Esoteric\utils\EvictingList;
use pocketmine\math\Vector3;

final class LocationData {

	/** @var int */
	public $entityRuntimeId;
	/** @var Vector3 */
	public $currentLocation;
	/** @var Vector3 */
	public $lastLocation;
	/** @var Vector3 */
	public $receivedLocation;
	/** @var int */
	public $newPosRotationIncrements = 0;
	/** @var int */
	public $isSynced = 0;
	/** @var EvictingList */
	public $history;
	/** @var float */
	public $locationOffset = 0.0;
	/** @var float */
	public $hitboxWidth = 0.3, $hitboxHeight = 1.8;
	/** @var bool */
	public $isHuman = false;

}