<?php

namespace ethaniccc\Esoteric\utils;

use ErrorException;
use pocketmine\math\Vector3;
use function abs;
use function acos;
use function array_splice;
use function array_sum;
use function ceil;
use function cos;
use function count;
use function floor;
use function fmod;
use function max;
use function min;
use function pack;
use function pow;
use function sin;
use function sort;
use function sqrt;
use function unpack;

final class MathUtils {

	public static function hypot(float $p1, float $p2): float {
		return sqrt($p1 * $p1 + $p2 * $p2);
	}

	public static function getDeviation(float ...$nums): float {
		$count = count($nums);
		if ($count === 0) {
			return 0.0;
		}
		$variance = 0;
		$average = array_sum($nums) / $count;
		foreach ($nums as $num) {
			$variance += pow($num - $average, 2);
		}
		return sqrt($variance / $count);
	}

	public static function getAverage(float ...$nums): float {
		return array_sum($nums) / count($nums);
	}

	public static function vectorAngle(Vector3 $a, Vector3 $b): float {
		try {
			$dot = min(max($a->dot($b) / ($a->length() * $b->length()), -1), 1);
			return acos($dot);
		} catch (ErrorException $e) {
			return -1;
		}
	}

	// see https://github.com/eldariamc/client/blob/c01d23eb05ed83abb4fee00f9bf603b6bc3e2e27/src/main/java/net/minecraft/entity/EntityLivingBase.java#L2129
	public static function directionVectorFromValues(float $yaw, float $pitch): Vector3 {
		$var2 = cos(-$yaw * 0.017453292 - M_PI);
		$var3 = sin(-$yaw * 0.017453292 - M_PI);
		$var4 = -(cos(-$pitch * 0.017453292));
		$var5 = sin(-$pitch * 0.017453292);
		return new Vector3($var3 * $var4, $var5, $var2 * $var4);
	}

	public static function getKurtosis(float ...$data): float {
		try {
			$sum = array_sum($data);
			$count = count($data);

			if ($sum === 0.0 or $count <= 2) {
				return 0.0;
			}

			$efficiencyFirst = $count * ($count + 1) / (($count - 1) * ($count - 2) * ($count - 3));
			$efficiencySecond = 3 * pow($count - 1, 2) / (($count - 2) * ($count - 3));
			$average = self::getAverage(...$data);

			$variance = 0.0;
			$varianceSquared = 0.0;

			foreach ($data as $number) {
				$variance += pow($average - $number, 2);
				$varianceSquared += pow($average - $number, 4);
			}

			return $efficiencyFirst * ($varianceSquared / pow($variance / $sum, 2)) - $efficiencySecond;
		} catch (ErrorException $e) {
			return 0.0;
		}
	}

	public static function getSkewness(float ...$data): float {
		$sum = array_sum($data);
		$count = count($data);

		$numbers = $data;
		sort($numbers);

		$mean = $sum / $count;
		$median = ($count % 2 !== 0) ? $numbers[$count * 0.5] : ($numbers[($count - 1) * 0.5] + $numbers[$count * 0.5]) * 0.5;
		$variance = self::getVariance(...$data);

		return $variance > 0 ? 3 * ($mean - $median) / $variance : 0;
	}

	public static function getVariance(float ...$data): float {
		$variance = 0;
		$count = count($data);
		if ($count === 0) {
			return 0.0;
		}
		$mean = array_sum($data) / $count;

		foreach ($data as $number) {
			$variance += pow($number - $mean, 2);
		}

		return $variance / $count;
	}

	public static function getOutliers(float ...$collection): float {
		$count = count($collection);
		$q1 = self::getMedian(...array_splice($collection, 0, (int) ceil($count * 0.5)));
		$q3 = self::getMedian(...array_splice($collection, (int) ceil($count * 0.5), $count));

		$iqr = abs($q1 - $q3);
		$lowThreshold = $q1 - 1.5 * $iqr;
		$highThreshold = $q3 + 1.5 * $iqr;

		$x = [];
		$y = [];

		foreach ($collection as $value) {
			if ($value < $lowThreshold) {
				$x[] = $value;
			} elseif ($value > $highThreshold) {
				$y[] = $value;
			}
		}

		return count($x) + count($y);
	}

	public static function getMedian(float ...$data): float {
		$count = count($data);
		if ($count === 0) {
			return 0.0;
		}

		sort($data);

		return ($count % 2 === 0) ? ($data[$count * 0.5] + $data[$count * 0.5 - 1]) * 0.5 : $data[$count * 0.5];
	}

	public static function gcdLong(float $a, float $b): float {
		return ($b <= 16384) ? $a : self::gcdLong($b, fmod($a, $b));
	}

	public static function getArrayGCD(float ...$nums): float {
		$count = count($nums);
		if ($count <= 1) {
			return 0.0;
		}
		$result = $nums[0];
		for ($i = 1; $i < $count; $i++) {
			$result = self::getGCD($nums[$i], $result);
		}
		return $result;
	}

	public static function getGCD(float $a, float $b): float {
		if ($a < $b) {
			return self::getGCD($b, $a);
		} elseif (abs($b) < 0.0001) {
			return $a;
		} else {
			return self::getGCD($b, $a - floor($a / $b) * $b);
		}
	}

	public static function wrap180(float $par0): float {
		$par0 = fmod($par0, 360);
		return $par0 + ($par0 >= 180.0 ? -360.0 : 360.0);
	}

	public static function getLiteralFloat(float $float): float {
		return unpack("G", pack("G", $float))[1];
	}

	/**
	 * @param Vector3 $eyePos - Eye pos of the entity
	 * @param Vector3 $pos - Target position to check possible interaction
	 * @param Vector3 $dV - Direction vector of the entity
	 * @param float $maxDistance - Distance to check interaction
	 * @param float|int $maxDiff
	 * @return bool - If the entity can interact with the position
	 */
	public static function canInteract(Vector3 $eyePos, Vector3 $pos, Vector3 $dV, float $maxDistance, float $maxDiff = M_SQRT3 * 0.5): bool {
		if ($eyePos->distanceSquared($pos) > $maxDistance ** 2) {
			return false;
		}

		$eyeDot = $dV->dot($eyePos);
		$targetDot = $dV->dot($pos);
		return ($targetDot - $eyeDot) >= -$maxDiff;
	}

	public static function clamp(float $val, float $min, float $max): float {
		return max($min, min($max, $val));
	}

}