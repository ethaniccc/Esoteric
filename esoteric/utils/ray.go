package utils

import (
	"github.com/go-gl/mathgl/mgl32"
)

type Ray struct {
	EntityPos mgl32.Vec3
	Direction mgl32.Vec3
}

func (r *Ray) Traverse(distance float32) mgl32.Vec3 {
	return r.EntityPos.Add(r.Direction.Mul(distance))
}