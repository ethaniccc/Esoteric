<?php

namespace ethaniccc\Esoteric\utils;

use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use function max;
use function sqrt;

class AABB extends AxisAlignedBB {

	public Vector3 $maxVector;
	public Vector3 $minVector;

	public function __construct(float $minX, float $minY, float $minZ, float $maxX, float $maxY, float $maxZ) {
		parent::__construct($minX, $minY ?? 0.0, $minZ, $maxX, $maxY, $maxZ);
		$this->minVector = new Vector3($minX, $minY, $minZ);
		$this->maxVector = new Vector3($maxX, $maxY, $maxZ);
	}

	public static function from(PlayerData $data): self {
		$pos = $data->currentLocation;
		return new AABB($pos->x - $data->hitboxWidth, $pos->y, $pos->z - $data->hitboxWidth, $pos->x + $data->hitboxWidth, $pos->y + $data->hitboxHeight, $pos->z + $data->hitboxWidth);
	}

	public static function fromPosition(Vector3 $pos, float $width = 0.3, float $height = 1.8): AABB {
		return new AABB($pos->x - $width, $pos->y, $pos->z - $width, $pos->x + $width, $pos->y + $height, $pos->z + $width);
	}

	public static function fromBlock(Block $block): AABB {
		$b = $block->getCollisionBoxes()[0] ?? null;
		if ($b !== null || count($block->getCollisionBoxes()) > 0) {
			return new AABB($b->minX, $b->minY, $b->minZ, $b->maxX, $b->maxY, $b->maxZ);
		} else {
			$pos = $block->getPos();
			return new AABB($pos->getX(), $pos->getY(), $pos->getZ(), $pos->getX() + 1, $pos->getY() + 1, $pos->getZ() + 1);
		}
	}

	public function clone(): AABB {
		return clone $this;
	}

	public function distanceFromVector(Vector3 $vector): float {
		$distX = max($this->minX - $vector->x, max(0, $vector->x - $this->maxX));
		$distY = max($this->minY - $vector->y, max(0, $vector->y - $this->maxY));
		$distZ = max($this->minZ - $vector->z, max(0, $vector->z - $this->maxZ));
		return sqrt(($distX ** 2) + ($distY ** 2) + ($distZ ** 2));
	}

	public function calculateIntercept(Vector3 $pos1, Vector3 $pos2): ?RayTraceResult {
		return $this->isVectorInside($pos1) ? new RayTraceResult($this, 0, clone MathUtils::$ZERO_VECTOR) : parent::calculateIntercept($pos1, $pos2);
	}

}