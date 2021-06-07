<?php

namespace ethaniccc\Esoteric\blocks;

use pocketmine\block\FenceGate;
use pocketmine\math\AxisAlignedBB;

class FenceGateOverride extends FenceGate {

    public function __construct(int $id, int $meta = 0, string $name = null, int $itemId = null) {
        parent::__construct($id, $meta, $name, $itemId);
    }

    protected function recalculateBoundingBox(): ?AxisAlignedBB {
        $isOpen = $this->getDamage() > 3; // closed fence gate meta states are 0 to 3
        return $isOpen ? null : parent::recalculateBoundingBox();
    }

}