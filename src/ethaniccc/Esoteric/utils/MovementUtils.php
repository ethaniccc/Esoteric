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
		$f3 = ($f3 ** 2) * min(1, $d4 / 0.4);
		$estimated = $estimated->add(new Vector3(0, $data->gravity * (-1 + $f3 * 0.75), 0));
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
	 * @return Vector3 - An estimated position of where the player should be next.
	 */
	public static function doCollisions(PlayerData $data): Vector3 {
		$dx = $data->currentMoveDelta->x;
		$dy = $data->currentMoveDelta->y;
		$dz = $data->currentMoveDelta->z;
		$movX = $dx;
		$movY = $dy;
		$movZ = $dz;

		$data->ySize *= 0.4;
		$oldBB = AABB::fromPosition($data->lastLocation, $data->hitboxWidth, $data->hitboxHeight);
		$oldBBClone = clone $oldBB;

		$world = $data->world;

		/* if ($data->onGround && $data->isSneaking) {
			for ($mov = 0.05; $dx != 0.0 && count(LevelUtils::checkBlocksInAABB($oldBB->offset($dx, -1, 0), $world, LevelUtils::SEARCH_SOLID)) === 0; $movX = $dx) {
				if ($dx < $mov and $dx >= -$mov) {
					$dx = 0;
				} elseif ($dx > 0) {
					$dx -= $mov;
				} else {
					$dx += $mov;
				}
			}
			for (; $dz != 0.0 and count(LevelUtils::checkBlocksInAABB($oldBB->offset(0, -1, $dz), $world, LevelUtils::SEARCH_SOLID)) === 0; $movZ = $dz) {
				if ($dz < $mov and $dz >= -$mov) {
					$dz = 0;
				} elseif ($dz > 0) {
					$dz -= $mov;
				} else {
					$dz += $mov;
				}
			}
		} */

		$list = LevelUtils::getCollisionBBList($oldBB->addCoord($dx, $dy, $dz), $world);

		foreach ($list as $bb) {
			$dy = $bb->calculateYOffset($oldBB, $dy);
		}

		$oldBB->offset(0, $dy, 0);

		$fallingFlag = $data->onGround || ($dy != $movY && $movY < 0);

		foreach ($list as $bb) {
			$dx = $bb->calculateXOffset($oldBB, $dx);
		}

		$oldBB->offset($dx, 0, 0);

		foreach ($list as $bb) {
			$dz = $bb->calculateZOffset($oldBB, $dz);
		}

		$oldBB->offset(0, 0, $dz);

		if ($fallingFlag && ($movX != $dx || $movZ != $dz)) {
			$cx = $dx;
			$cy = $dy;
			$cz = $dz;
			$dx = $movX;
			$dy = MovementConstants::STEP_HEIGHT;
			$dz = $movZ;

			$oldBBClone2 = clone $oldBB;
			$oldBB->setBB($oldBBClone);

			$list = LevelUtils::getCollisionBBList($oldBB->addCoord($dx, $dy, $dz), $world);

			foreach ($list as $bb) {
				$dy = $bb->calculateYOffset($oldBB, $dy);
			}

			$oldBB->offset(0, $dy, 0);

			foreach ($list as $bb) {
				$dx = $bb->calculateXOffset($oldBB, $dx);
			}

			$oldBB->offset($dx, 0, 0);

			foreach ($list as $bb) {
				$dz = $bb->calculateZOffset($oldBB, $dz);
			}

			$oldBB->offset(0, 0, $dz);

			$reverseDY = -$dy;
			foreach ($list as $bb) {
				$reverseDY = $bb->calculateYOffset($oldBB, $reverseDY);
			}
			$dy += $reverseDY;
			$oldBB->offset(0, $reverseDY, 0);

			if (($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)) {
				/* $dx = $cx;
				$dy = $cy;
				$dz = $cz; */
				$oldBB->setBB($oldBBClone2);
			} else {
				$data->ySize += $dy;
			}
		}

		$position = new Vector3(0, 0, 0);
		$position->x = ($oldBB->minX + $oldBB->maxX) / 2;
		$position->y = $oldBB->minY - $data->ySize;
		$position->z = ($oldBB->minZ + $oldBB->maxZ) / 2;
		return $position;
	}

}