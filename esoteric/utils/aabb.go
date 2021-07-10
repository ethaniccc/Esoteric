package utils

import "github.com/go-gl/mathgl/mgl32"

type AxisAlignedBB struct {
	MinVector mgl32.Vec3
	MaxVector mgl32.Vec3
}

func AABBFromPosition(p mgl32.Vec3, width, height float32) AxisAlignedBB {
	return AxisAlignedBB{
		MinVector: mgl32.Vec3{p.X() - width, p.Y(), p.Z() - width},
		MaxVector: mgl32.Vec3{p.X() + width, p.Y() + height, p.Z() + width},
	}
}