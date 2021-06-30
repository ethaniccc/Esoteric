import { Packet } from "bdsx/bds/packet";
import { MinecraftPacketIds } from "bdsx/bds/packetids";
import { LevelChunkPacket, NetworkStackLatencyPacket, TickSyncPacket } from "bdsx/bds/packets";
import { NativePointer } from "bdsx/core";
import { log, sleep } from "..";
import { PlayerData } from "../data/PlayerData";

export class OutboundExecutor {

    constructor(
        public data: PlayerData
    ) {}

    public async execute(ptr: Packet) {
    }

    public async executeRaw(buffer: NativePointer) {
        var pid = buffer.readVarUint() & 0x3ff;
        if (pid === MinecraftPacketIds.LevelChunk) {
            var chunkX = buffer.readVarInt();
            var chunkZ = buffer.readVarInt();
            var subChunkCount = buffer.readVarUint();
            var cacheEnabled = buffer.readBoolean();
            if (cacheEnabled) {
                var count = buffer.readVarInt();
                var hashes = [];
                for (var i = 0; i < count; i++) {
                    hashes.push(buffer.readUint64AsFloat());
                }
            }
            //var extraPayload = buffer.readString().toString();
            //log("Chunk is being sent (x=" + chunkX + " z=" + chunkZ + " subChunks=" + subChunkCount + " cache=" + cacheEnabled + ")");
        }
    }

}