import { Vector3 } from "./Vector3";

export class AxisAlignedBB {

    constructor(
        public min: Vector3,
        public max: Vector3
    ){}

    public expand(x: number = 0, y: number = 0, z: number = 0): AxisAlignedBB {
        this.min.subtract(x, y, z);
        this.max.add(x, y, z);
        return this;
    }

}