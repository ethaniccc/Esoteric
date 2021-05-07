<?php

namespace ethaniccc\Esoteric\utils;

use Generator;
use pocketmine\block\UnknownBlock;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\World;
use function ceil;
use function floor;

final class LevelUtils {

	public const SEARCH_ALL = 0;
	public const SEARCH_TRANSPARENT = 1;
	public const SEARCH_SOLID = 2;

	/**
	 * @param AxisAlignedBB $AABB
	 * @param World $level
	 * @param int $searchOption
	 * @param bool $first
	 * @return Generator
	 */
	public static function checkBlocksInAABB(AxisAlignedBB $AABB, World $level, int $searchOption, bool $first = false): Generator {
		$minX = (int) floor($AABB->minX);
		$maxX = (int) ceil($AABB->maxX);
		$minY = (int) floor($AABB->minY);
		$maxY = (int) ceil($AABB->maxY);
		$minZ = (int) floor($AABB->minZ);
		$maxZ = (int) ceil($AABB->maxZ);
		$curr = $level->getBlockAt($minX, $minY, $minZ);
		switch ($searchOption) {
			case self::SEARCH_ALL:
				yield $curr;
				if ($first)
					return;
				for ($x = $minX; $x < $maxX; ++$x) {
					for ($y = $minY; $y < $maxY; ++$y) {
						for ($z = $minZ; $z < $maxZ; ++$z) {
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
				for ($x = $minX; $x < $maxX; ++$x) {
					for ($y = $minY; $y < $maxY; ++$y) {
						for ($z = $minZ; $z < $maxZ; ++$z) {
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