<?php

namespace ethaniccc\Esoteric\blocks;

use pocketmine\block\FenceGate;
use pocketmine\math\AxisAlignedBB;

/**
 * Class FenceGateOverride
 * @package ethaniccc\Esoteric\blocks
 */
class FenceGateOverride extends FenceGate {


	/**
	 * FenceGateOverride constructor.
	 *
	 * @param int         $id
	 * @param int         $meta
	 * @param string|null $name
	 * @param int|null    $itemId
	 */
	public function __construct(int $id, int $meta = 0, string $name = null, int $itemId = null) {
		parent::__construct($id, $meta, $name, $itemId);
	}

	/**
	 * @return AxisAlignedBB|null
	 */
	protected function recalculateBoundingBox(): ?AxisAlignedBB {
		$isOpen = $this->getDamage() > 3; // closed fence gate meta states are 0 to 3
		return $isOpen ? null : parent::recalculateBoundingBox();
	}

}