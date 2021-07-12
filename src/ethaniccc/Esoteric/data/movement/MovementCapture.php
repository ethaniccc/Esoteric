<?php

namespace ethaniccc\Esoteric\data\movement;

use pocketmine\level\Location;
use pocketmine\math\Vector3;

final class MovementCapture {

	public static function dummy(): self {
		return new self(Location::fromObject(new Vector3(0, 0, 0)), new Vector3(0, 0, 0), 0, 0);
	}

	/** @var Location */
	public $location;
	/** @var Vector3 */
	public $movementDelta;
	/** @var float */
	public $yawDelta;
	/** @var float */
	public $pitchDelta;

	public function __construct(Location $location, Vector3 $movementDelta, float $yawDelta, float $pitchDelta) {
		$this->location = $location;
		$this->movementDelta = $movementDelta;
		$this->yawDelta = $yawDelta;
		$this->pitchDelta = $pitchDelta;
	}

}