import { NetworkIdentifier } from "bdsx/bds/networkidentifier";
import { Packet } from "bdsx/bds/packet";
import { MinecraftPacketIds } from "bdsx/bds/packetids";
import { BinaryStream } from "bdsx/bds/stream";
import { NativePointer } from "bdsx/core";
import { events } from "bdsx/event";
import { log } from ".";
import { DataStorage } from "./data/DataStorage";
import { InventoryTransactionChangeSlot, InventoryTransactionWrapper } from "./wrappers/Wrappers";

let inboundWrapper: Packet|null = null;
let outboundWrapper: Packet|null = null;

const inboundPacketHandleCallable = (ptr: Packet, identifier: NetworkIdentifier) => {
    var data = DataStorage.INSTANCE.get(identifier);
    if (data === null) {
        log("Data not found for " + identifier + " - creating...");
        data = DataStorage.INSTANCE.add(identifier);
    }
    if (inboundWrapper === null) {
        data.packetInboundQueue.push(ptr);
    } else {
        data.packetInboundQueue.push(inboundWrapper);
    }
    inboundWrapper = null;
    // TODO: Checks
};

const outboundPacketHandleCallable = (ptr: Packet, identifier: NetworkIdentifier) => {
    var data = DataStorage.INSTANCE.get(identifier);
    if (data === null) {
        log("Data not found for " + identifier + " - creating...");
        data = DataStorage.INSTANCE.add(identifier);
    }
    if (outboundWrapper === null) {
        data.packetOutboundQueue.push(ptr);
    } else {
        data.packetOutboundQueue.push(outboundWrapper);
    }
    outboundWrapper = null;
};

export class PacketListener {

    public static init() {
        for (var i = 1; i <= 255; i++) {
            events.packetAfter(i).on((ptr: Packet, identifier: NetworkIdentifier) => {
                inboundPacketHandleCallable(ptr, identifier);
            });
            events.packetSend(i).on((ptr: Packet, identifier: NetworkIdentifier) => {
                outboundPacketHandleCallable(ptr, identifier);
            });
        }

        // For custom packet wrappers - packets that aren't implemented into BDSX
        events.packetRaw(MinecraftPacketIds.InventoryTransaction).on((ptr, size) => {
            ptr.move(1); // ignore packet ID, we already know what it is
            var wrapper = new InventoryTransactionWrapper();
            wrapper.requestId = ptr.readVarInt();
            if (wrapper.requestId !== 0) {
                for (var i = 0; i < ptr.readVarUint(); i++) {
                    wrapper.requestedChangedSlots.push(InventoryTransactionChangeSlot.read(ptr));
                }
            }
            var transactionType = ptr.readVarUint();
            switch (transactionType) {
                // TODO: Transaction data. This is going to be a pain in the ass
            }
            inboundWrapper = wrapper;
        });

        events.networkDisconnected.on((identifier: NetworkIdentifier) => {
            var data = DataStorage.INSTANCE.get(identifier);
            if (data !== null) {
                data.isClosed = true;
            }
        });
    }

}