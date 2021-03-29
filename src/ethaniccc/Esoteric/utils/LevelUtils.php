<?php

namespace ethaniccc\Esoteric\utils;

use pocketmine\block\Block;
use pocketmine\block\UnknownBlock;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use Generator;

final class LevelUtils{

    public const SEARCH_ALL = 0;
    public const SEARCH_TRANSPARENT = 1;
    public const SEARCH_SOLID = 2;

    /**
     * @param AxisAlignedBB $AABB
     * @param Level $level
     * @param int $searchOption
     * @param bool $first
     * @return Generator
     */
    public static function checkBlocksInAABB(AxisAlignedBB $AABB, Level $level, int $searchOption, bool $first = false) : Generator{
        $minX = (int) floor($AABB->minX);
        $maxX = (int) ceil($AABB->maxX);
        $minY = (int) floor($AABB->minY);
        $maxY = (int) ceil($AABB->maxY);
        $minZ = (int) floor($AABB->minZ);
        $maxZ = (int) ceil($AABB->maxZ);
        $curr = $level->getBlockAt($minX, $minY, $minZ, false, false);
        if($first){
            switch($searchOption){
                case self::SEARCH_ALL:
                    yield $curr;
                    return ;
                case self::SEARCH_TRANSPARENT:
                    if($curr->hasEntityCollision()){
                        yield $curr;
                        return;
                    }
                    for($x = $minX; $x < $maxX; $x++){
                        for($y = $minY; $y < $maxY; $y++){
                            for($z = $minZ; $z < $maxZ; $z++){
                                $block = $level->getBlockAt($x, $y, $z, false, false);
                                if($block->hasEntityCollision()){
                                    yield $block;
                                    return;
                                }
                            }
                        }
                    }
                    return;
                case self::SEARCH_SOLID:
                    if($curr->isSolid() || $curr instanceof UnknownBlock){
                        yield $curr;
                        return;
                    }
                    for($x = $minX; $x < $maxX; $x++){
                        for($y = $minY; $y < $maxY; $y++){
                            for($z = $minZ; $z < $maxZ; $z++){
                                $block = $level->getBlockAt($x, $y, $z, false, false);
                                if($block->isSolid() || $block instanceof UnknownBlock){
                                    yield $block;
                                    return;
                                }
                            }
                        }
                    }
                    return;
            }
        } else {
            switch($searchOption){
                case self::SEARCH_ALL:
                    yield $curr;
                    for($x = $minX; $x < $maxX; $x++){
                        for($y = $minY; $y < $maxY; $y++){
                            for($z = $minZ; $z < $maxZ; $z++){
                                $block = $level->getBlockAt($x, $y, $z, false, false);
                                yield $block;
                            }
                        }
                    }
                    return;
                case self::SEARCH_TRANSPARENT:
                    if($curr->hasEntityCollision()){
                        yield $curr;
                    }
                    for($x = $minX; $x < $maxX; $x++){
                        for($y = $minY; $y < $maxY; $y++){
                            for($z = $minZ; $z < $maxZ; $z++){
                                $block = $level->getBlockAt($x, $y, $z, false, false);
                                if($block->hasEntityCollision()){
                                    yield $block;
                                }
                            }
                        }
                    }
                    return;
                case self::SEARCH_SOLID:
                    if($curr->isSolid() || $curr instanceof UnknownBlock){
                        yield $curr;
                    }
                    for($x = $minX; $x < $maxX; $x++){
                        for($y = $minY; $y < $maxY; $y++){
                            for($z = $minZ; $z < $maxZ; $z++){
                                $block = $level->getBlockAt($x, $y, $z, false, false);
                                if($block->isSolid() || $block instanceof UnknownBlock){
                                    yield $block;
                                }
                            }
                        }
                    }
                    return;
            }
        }
    }

}