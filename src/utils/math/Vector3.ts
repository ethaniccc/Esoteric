import { BlockPos } from "bdsx/bds/blockpos";

export class Vector3 {

    constructor(
        public x: number = 0,
        public y: number = 0,
        public z: number = 0
    ){}

    public clone(): Vector3 {
        return new Vector3(this.x, this.y, this.z);
    }

    public add(x: number = 0, y: number = 0, z: number = 0): Vector3 {
        this.x += x;
        this.y += y;
        this.z += z;
        return this;
    }

    public addVector(vec: Vector3): Vector3 {
        return this.add(vec.x, vec.y, vec.z);
    }

    public subtract(x: number = 0, y: number = 0, z: number = 0): Vector3 {
        this.x -= x;
        this.y -= y;
        this.z -= z;
        return this;
    }

    public subtractVector(vec: Vector3): Vector3 {
        return this.subtract(vec.x, vec.y, vec.z);
    }

    public floor(): Vector3 {
        this.x = Math.floor(this.x);
        this.y = Math.floor(this.y);
        this.z = Math.floor(this.z);
        return this;
    }

    public toBlockPos(): BlockPos {
        var clone = this.clone().floor();
        return BlockPos.create(clone.x, clone.y, clone.z);
    }

}