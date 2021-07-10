package data

import (
	"github.com/ethaniccc/esoteric/esoteric/utils"
	"github.com/go-gl/mathgl/mgl32"
)

type Player struct {
	Direction mgl32.Vec3
	Position mgl32.Vec3
	HitBoxWidth float32
	HitBoxHeight float32
	CurrentTick int
}

func (p Player) Ray() utils.Ray {
	return utils.Ray{EntityPos: p.Position.Add(mgl32.Vec3{0, 1.62, 0}), Direction: p.Direction}
}

func (p Player) AABB() utils.AxisAlignedBB {
	return utils.AABBFromPosition(p.Position, p.HitBoxWidth, p.HitBoxHeight)
}

func (p *Player) Tick() {
	p.CurrentTick++
}