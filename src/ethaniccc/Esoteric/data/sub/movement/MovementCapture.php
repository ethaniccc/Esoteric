<?php

namespace ethaniccc\Esoteric\data\sub\movement;

use ethaniccc\Esoteric\utils\MathUtils;
use pocketmine\level\Location;
use pocketmine\math\Vector3;

final class MovementCapture {

	/** @var Vector3 */
	public $location;
	/** @var float */
	public $yaw;
	/** @var float */
	public $pitch;
	/** @var Vector3 */
	public $directionVector;

	public function __construct(Location $location) {
		$this->location = $location->asVector3();
		$this->yaw = $location->yaw;
		$this->pitch = $location->pitch;
		$this->directionVector = MathUtils::directionVectorFromValues($this->yaw, $this->pitch);
	}

}