import { Packet } from "bdsx/bds/packet";
import { MinecraftPacketIds } from "bdsx/bds/packetids";
import { Encoding } from "bdsx/common";
import { NativePointer } from "bdsx/core";
import { bin64_t, bool_t, int32_t, int64_as_float_t } from "bdsx/nativetype";
import { Buffer } from "buffer";
import { TextEncoder } from "util";
import { Inflate } from "zlibt2";
import { SetActorMotionPacket } from "bdsx/bds/packets";
import { RawDeflate, RawInflate } from 'zlibt2/raw';
import { RawPacket } from 'bdsx/rawpacket';
import { Vec3 } from "bdsx/bds/blockpos";
import { nativeClass, nativeField } from "bdsx/nativeclass";

export class InventoryTransactionWrapper extends Packet {

    // RequestID of the packet.
    requestId:int32_t;
    // The requested changed slots in the packet - this will only be filled
    // when the RequestID is not 0
    requestedChangedSlots: Array<InventoryTransactionChangeSlot> = [];
}

export class InventoryTransactionChangeSlot {

    constructor(
        public containerId: number,
        public changedSlotIndexes: Array<number>
    ){}

    public static read(ptr: NativePointer) {
        var containerId = ptr.readUint8();
        var changedSlots:number[] = [];
        for (var i = 0; i < ptr.readVarUint(); i++) {
            changedSlots.push(ptr.readUint8());
        }
        return new InventoryTransactionChangeSlot(containerId, changedSlots);
    }

}

export abstract class TransactionData {

    public decode(ptr: NativePointer): void {
        var actionCount = ptr.readVarUint();
        for (var i = 0; i < actionCount; ++i) {
            // TODO: Network inventory actions
        }
        this.decodeData();
    }

    public abstract decodeData(): void;

}

export class NetworkInventoryAction {

    constructor(
        public sourceType: number,
        public windowId: number,
        public sourceFlags: number,
    ){}

}

export class BatchPacket extends NativePointer {

    public packets: Array<Uint8Array> = [];

    public addPacket(buffer: Uint8Array): void {
        this.packets.push(buffer);
    }

    public removePacket(buffer: Uint8Array): void {
        for (var key in this.packets) {
            if (this.packets[key] === buffer) {
                delete this.packets[key];
                break;
            }
        }
    }

    public clear(): void {
        this.packets = [];
    }

    public encode(): RawPacket {
        this.writeUint8(BatchPID);
        var raw = "";
        for (var buffer of this.packets) {
            this.writeVarUint(buffer.length);
            raw = raw + this.readVarUint() + buffer;
        }
        var encoded = (new TextEncoder()).encode(raw);
        var zlibEncoder = new RawDeflate(encoded);
        var zlibEncoded = zlibEncoder.compress() as Uint8Array;
        var packet = new RawPacket();
        packet.writeUint8(BatchPID);
        packet.write(zlibEncoded);
        return packet;
    }

}

export class SetActorMotionWrapper extends Packet {

    public entityRuntimeId: number;
    public motion: Vec3;

}

export class SetLocalPlayerAsInitializedWrapper extends Packet {

    public entityRuntimeId: number;

}

@nativeClass(null)
export class NetworkStackLatencyWrapper extends Packet {

    @nativeField(int64_as_float_t)
    public timestamp: int64_as_float_t;
    @nativeField(bool_t)
    public needsResponse: bool_t;

}

export class LevelChunkWrapper extends Packet {

    public chunkX: number;
    public chunkZ: number;

}

export const BatchPID = 0xfe;