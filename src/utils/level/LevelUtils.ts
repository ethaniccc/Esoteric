export class LevelUtils {

    public static chunkHash(x: number, z: number): number {
        return ((x & 0xFFFFFFFF) << 32) | (z & 0xFFFFFFFF);
    }

}