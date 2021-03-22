<?php

namespace ethaniccc\Esoteric\data\sub\location;

use pocketmine\math\Vector3;

final class LocationData{

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
    /** @var bool */
    public $isSynced = false;

}