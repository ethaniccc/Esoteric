<?php

namespace ethaniccc\Esoteric\utils;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use function cos;
use function max;
use function sin;
use function sqrt;

final class MovementUtils {

	public static function moveFlying(float $forward, float $strafe, float $friction, float $yaw): Vector3 {
		$var1 = ($forward ** 2) + ($strafe ** 2);
		if ($var1 >= 1E-4) {
			$var1 = max(sqrt($var1), 1);
			$var1 = $friction / $var1;
			$strafe *= $var1;
			$forward *= $var1;
			$var2 = sin($yaw * M_PI / 180);
			$var3 = cos($yaw * M_PI / 180);
			return new Vector3($strafe * $var3 - $forward * $var2, 0, $forward * $var3 + $strafe * $var2);
		}
		return new Vector3(0, 0, 0);
	}

	/**
	 * @param PlayerData $data
	 * @return Vector3
	 * @link https://github.com/MWHunter/Grim/blob/06122780d09e78e7a7a52cbee694b8d6f52b69f1/src/main/java/ac/grim/grimac/checks/predictionengine/movementTick/MovementTicker.java#L347-L371
	 * @author DefineOutside#4497 - Literal M.A.C legend
	 */
	public static function getEstimatedElytraMovement(PlayerData $data): Vector3 {
		$yRotRadians = $data->currentPitch * MovementConstants::UNKNOWN_1;
		$directionVector = $data->directionVector;
		$estimated = clone $data->lastMoveDelta;
		$d2 = MathUtils::hypot($directionVector->x, $directionVector->z);
		$d3 = MathUtils::hypot($estimated->x, $estimated->z);
		$d4 = $directionVector->length();
		$f3 = cos($yRotRadians);
		$f3 = $f3 * $f3 * min(1, $d4 / 0.4);
		$estimated = $estimated->add(new Vector3(0, MovementConstants::NORMAL_GRAVITY * (-1 + $f3 * 0.75), 0));
		if ($estimated->y < 0 && $d2 > 0) {
			$d5 = $estimated->y * -0.1 * $f3;
			$estimated = $estimated->add(new Vector3($directionVector->x * $d5 / $d2, $d5, $directionVector->z * $d5 / $d2));
		}

		if ($yRotRadians < 0 && $d2 > 0) {
			$d5 = $d3 * -sin($yRotRadians) * 0.04;
			$estimated = $estimated->add(new Vector3(-$directionVector->x * $d5 / $d2, $d5 * 3.2, -$directionVector->z * $d5 / $d2));
		}

		if ($d2 > 0) {
			$estimated = $estimated->add(new Vector3(($directionVector->x / $d2 * $d3 - $estimated->x) * 0.1, 0, ($directionVector->z / $d2 * $d3 - $estimated->z) * 0.1));
		}

		return $estimated;
	}

	/**
	 * @param PlayerData $data
	 * @return Vector3 - An estimated position of where the player should be next.
	 */
	public static function doCollisions(PlayerData $data): Vector3 {
		$delta = $data->currentMoveDelta;
		$dx = $delta->x;
		$dy = $delta->y;
		$dz = $delta->z;
		$movX = $dx;
		$movY = $dy;
		$movZ = $dz;
		/** @var Block[] $blockList */
		$blockList = array_filter(iterator_to_array(LevelUtils::checkBlocksInAABB($data->lastBoundingBox, $data->world, LevelUtils::SEARCH_ALL)), function (Block $block) use ($data): bool {
			return $block->collidesWithBB($data->lastBoundingBox);
		});
		$list = [];
		foreach ($blockList as $block) {
			foreach ($block->getCollisionBoxes() as $box) {
				$list[] = $box;
			}
		}
		foreach ($list as $bb) {
			$dx = $bb->calculateXOffset($data->lastBoundingBox, $dx);
			$dy = $bb->calculateXOffset($data->lastBoundingBox, $dy);
			$dz = $bb->calculateXOffset($data->lastBoundingBox, $dz);
		}
		$fallingFlag = $data->onGround || ($dy != $movY && $movY < 0);
		$horizontalFlag = $dz != $movZ || $dx != $movX;
		if ($fallingFlag || $horizontalFlag) {
			$cx = $dx;
			$cy = $dy;
			$cz = $dz;
			$dx = $movX;
			$dy = MovementConstants::STEP_HEIGHT;
			$dz = $movZ;
			/** @var Block[] $blockList */
			$blockList = array_filter(iterator_to_array(LevelUtils::checkBlocksInAABB($data->lastBoundingBox->expandedCopy(0, 0, 0)->addCoord($dx, $dy, $dz), $data->world, LevelUtils::SEARCH_ALL)), function (Block $block) use ($data): bool {
				return $block->collidesWithBB($data->lastBoundingBox);
			});
			$list = [];
			foreach ($blockList as $block) {
				foreach ($block->getCollisionBoxes() as $box) {
					$list[] = $box;
				}
			}
			foreach ($list as $bb) {
				$dx = $bb->calculateXOffset($data->lastBoundingBox, $dx);
				$dy = $bb->calculateXOffset($data->lastBoundingBox, $dy);
				$dz = $bb->calculateXOffset($data->lastBoundingBox, $dz);
			}
			$reverseDY = -$dy;
			foreach ($list as $bb) {
				$reverseDY = $bb->calculateYOffset($data->lastBoundingBox, $reverseDY);
			}
			$dy += $reverseDY;
			if (($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)) {
				$dx = $cx;
				$dy = $cy;
				$dz = $cz;
			}
		}
		return $data->lastLocation->add($dx, $dy, $dz);
	}

}