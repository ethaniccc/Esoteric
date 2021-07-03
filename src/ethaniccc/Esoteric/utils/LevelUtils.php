<?php

namespace ethaniccc\Esoteric\utils;

use Generator;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\UnknownBlock;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\World;
use function ceil;
use function floor;

final class LevelUtils {

	public const SEARCH_ALL = 0;
	public const SEARCH_TRANSPARENT = 1;
	public const SEARCH_SOLID = 2;

	public static BlockBreakInfo $ZERO_BREAK_INFO;

	public static function checkBlocksInAABB(AxisAlignedBB $AABB, World $level, int $searchOption, bool $first = false) : Generator {
		$minX = floor($AABB->minX - 1);
		$maxX = ceil($AABB->maxX + 1);
		$minY = floor($AABB->minY - 1);
		$maxY = ceil($AABB->maxY + 1);
		$minZ = floor($AABB->minZ - 1);
		$maxZ = ceil($AABB->maxZ + 1);
		$curr = $level->getBlockAt($minX, $minY, $minZ);
		switch ($searchOption) {
			case self::SEARCH_ALL:
				yield $curr;
				if ($first)
					return;
				for ($x = $minX; $x <= $maxX; ++$x) {
					for ($y = $minY; $y <= $maxY; ++$y) {
						for ($z = $minZ; $z <= $maxZ; ++$z) {
							yield $level->getBlockAt($x, $y, $z, false, false);
						}
					}
				}
				return;
			case self::SEARCH_TRANSPARENT:
				if ($curr->hasEntityCollision()) {
					yield $curr;
					if ($first)
						return;
				}
				for ($x = $minX; $x <= $maxX; ++$x) {
					for ($y = $minY; $y <= $maxY; ++$y) {
						for ($z = $minZ; $z <= $maxZ; ++$z) {
							$block = $level->getBlockAt($x, $y, $z, false, false);
							if ($block->hasEntityCollision()) {
								yield $block;
								if ($first)
									return;
							}
						}
					}
				}
				return;
			case self::SEARCH_SOLID:
				if ($curr->isSolid() || $curr instanceof UnknownBlock) {
					yield $curr;
					if ($first)
						return;
				}
				for ($x = $minX; $x < $maxX; ++$x) {
					for ($y = $minY; $y < $maxY; ++$y) {
						for ($z = $minZ; $z < $maxZ; ++$z) {
							$block = $level->getBlockAt($x, $y, $z, false, false);
							if ($block->isSolid() || $block instanceof UnknownBlock) {
								yield $block;
								if ($first)
									return;
							}
						}
					}
				}
		}
	}

}