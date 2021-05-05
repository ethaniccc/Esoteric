package utils

import (
	"github.com/go-gl/mathgl/mgl64"
	"math"
	"sort"
)

// Deviation ...
func Deviation(nums []float64) float64 {
	count := float64(len(nums))
	if count == 0 {
		return 0.0
	}
	variance := float64(0)
	average := Sum(nums) / count
	for _, num := range nums {
		variance += math.Pow(num-average, 2)
	}
	return math.Sqrt(variance / count)
}

// VectorAngle ...
func VectorAngle(a, b mgl64.Vec3) float64 {
	dot := Min(Max(a.Dot(b)/(a.Len()*b.Len()), -1), 1)
	return math.Acos(dot)
}

// Average ...
func Average(nums []float64) float64 {
	return Sum(nums) / float64(len(nums))
}

// DirectionVectorFromValues ...
func DirectionVectorFromValues(yaw, pitch float64) mgl64.Vec3 {
	m := math.Cos(pitch)

	return mgl64.Vec3{
		-m * math.Sin(yaw),
		-math.Sin(pitch),
		m * math.Cos(yaw),
	}.Normalize()
}

// Kurtosis ...
func Kurtosis(data []float64) float64 {
	sum := Sum(data)
	count := float64(len(data))

	if sum == 0.0 || count <= 2 {
		return 0.0
	}

	efficiencyFirst := count * (count + 1) / ((count - 1) * (count - 2) * (count - 3))
	efficiencySecond := 3 * math.Pow(count-1, 2) / ((count - 2) * (count - 3))
	average := Average(data)

	var variance, varianceSquared float64
	for _, number := range data {
		variance += math.Pow(average-number, 2)
		varianceSquared += math.Pow(average-number, 4)
	}

	return efficiencyFirst*(varianceSquared/math.Pow(variance/sum, 2)) - efficiencySecond
}

// Skewness ...
func Skewness(data []float64) (skewness float64) {
	sum := Sum(data)
	count := float64(len(data))

	mean := sum / count
	median := Median(data)
	variance := Variance(data)
	if variance > 0 {
		skewness = 3 * (mean - median) / variance
	}

	return
}

// Variance ...
func Variance(data []float64) (variance float64) {
	count := float64(len(data))
	if count == 0 {
		return 0.0
	}
	mean := Sum(data) / count

	for _, number := range data {
		variance += math.Pow(number-mean, 2)
	}

	return variance / count
}

// Outliers ...
func Outliers(collection []float64) int {
	count := float64(len(collection))
	q1 := Median(Splice(collection, 0, int(math.Ceil(count*0.5))))
	q3 := Median(Splice(collection, int(math.Ceil(count*0.5)), int(count)))

	iqr := math.Abs(q1 - q3)
	lowThreshold := q1 - 1.5*iqr
	highThreshold := q3 + 1.5*iqr

	var x, y []float64

	for _, value := range collection {
		if value < lowThreshold {
			x = append(x, value)
		} else if value > highThreshold {
			y = append(y, value)
		}
	}

	return len(x) + len(y)
}

// Splice ...
func Splice(data []float64, offset int, length int) []float64 {
	if offset > len(data) {
		return []float64{}
	}
	end := offset + length
	if end < len(data) {
		return data[offset:end]
	}
	return data[offset:]
}

// Median ...
func Median(data []float64) (median float64) {
	count := float64(len(data))
	if count == 0 {
		return 0.0
	}

	sort.Float64s(data)
	median = (data[int((count-1)*0.5)] + data[int(count*0.5)]) * 0.5
	if int(count)%2 != 0 {
		median = data[int(count*0.5)]
	}

	return
}

// GCDLong ...
func GCDLong(a, b float64) float64 {
	if b <= 16384 {
		return a
	}
	return GCDLong(b, math.Mod(a, b))
}

// ArrayGCD
func ArrayGCD(nums []float64) float64 {
	count := len(nums)
	if count <= 1 {
		return 0.0
	}
	result := nums[0]
	for i := 1; i < count; i++ {
		result = GCD(nums[i], result)
	}
	return result
}

// GCD
func GCD(a, b float64) float64 {
	if a < b {
		return GCD(b, a)
	} else if math.Abs(b) < 0.0001 {
		return a
	} else {
		return GCD(b, a-math.Floor(a/b)*b)
	}
}

// Wrap180 ...
func Wrap180(par0 float64) float64 {
	par0 = math.Mod(par0, 360)
	par1 := 360.0
	if par0 >= 180.0 {
		par1 = -360.0
	}
	return par0 + par1
}

// CanInteract ...
func CanInteract(eyePos, pos, dV mgl64.Vec3, maxDistance, maxDiff float64) bool {
	if maxDiff == 0 {
		maxDiff = math.Sqrt(3) * 0.5
	}
	if Distance(eyePos, pos) > math.Pow(maxDistance, 2) {
		return false
	}

	eyeDot := dV.Dot(eyePos)
	targetDot := dV.Dot(pos)
	return (targetDot - eyeDot) >= -maxDiff
}

// Distance ...
func Distance(a, b mgl64.Vec3) float64 {
	xDiff, yDiff, zDiff := b[0]-a[0], b[1]-a[1], b[2]-a[2]
	return math.Sqrt(xDiff*xDiff + yDiff*yDiff + zDiff*zDiff)
}

// Clamp ...
func Clamp(val, min, max float64) float64 {
	return Max(min, Min(max, val))
}

// Sum ...
func Sum(array []float64) (result float64) {
	for _, v := range array {
		result += v
	}
	return result
}

// Max ...
func Max(array ...float64) float64 {
	var max = array[0]
	for _, value := range array {
		if max < value {
			max = value
		}
	}
	return max
}

// Min ...
func Min(array ...float64) float64 {
	var min = array[0]
	for _, value := range array {
		if min > value {
			min = value
		}
	}
	return min
}
