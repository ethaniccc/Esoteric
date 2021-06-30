import { Packet } from "bdsx/bds/packet";
import { NativePointer } from "bdsx/core";
import { NativeClass, nativeClass, nativeField } from "bdsx/nativeclass";
import { int32_t } from "bdsx/nativetype";

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