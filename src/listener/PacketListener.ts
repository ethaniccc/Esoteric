import { Vec3 } from "bdsx/bds/blockpos";
import { NetworkIdentifier } from "bdsx/bds/networkidentifier";
import { Packet } from "bdsx/bds/packet";
import { MinecraftPacketIds } from "bdsx/bds/packetids";
import { NativePointer } from "bdsx/core";
import { events } from "bdsx/event";
import { log } from "../..";
import { DataStorage } from "../data/DataStorage";
import { InventoryTransactionChangeSlot, InventoryTransactionWrapper, LevelChunkWrapper, SetActorMotionWrapper, SetLocalPlayerAsInitializedWrapper } from "../wrappers/Wrappers";

let inboundWrapper: Packet|null = null;
let outboundWrapper: Packet|null = null;

let target: NetworkIdentifier|null = null;

const inboundPacketHandleCallable = (ptr: Packet, identifier: NetworkIdentifier) => {
    var data = DataStorage.INSTANCE.get(identifier);
    if (data === null) {
        log("Data not found for " + identifier + " - creating...");
        data = DataStorage.INSTANCE.add(identifier);
    }
    if (inboundWrapper === null) {
        data.inboundExecutor.execute(ptr);
    } else {
        data.inboundExecutor.execute(inboundWrapper);
    }
    inboundWrapper = null;
    // TODO: Checks
};

const outboundPacketHandleCallable = (identifier: NetworkIdentifier) => {
    var data = DataStorage.INSTANCE.get(identifier);
    if (data === null) {
        log("Data not found for " + identifier + " - creating...");
        data = DataStorage.INSTANCE.add(identifier);
    }
    if (outboundWrapper !== null && data.esotericPackets <= 0) {
        data.outboundExecutor.execute(outboundWrapper);
    }
    data.esotericPackets--;
};

export class PacketListener {

    public static init() {
        for (var i = 1; i <= 255; i++) {
            events.packetAfter(i).on((ptr: Packet, identifier: NetworkIdentifier) => {
                inboundPacketHandleCallable(ptr, identifier);
            });
            events.packetSend(i).on((ptr: Packet, identifier: NetworkIdentifier) => {
                outboundWrapper = ptr;
                target = identifier;
                var data = DataStorage.INSTANCE.get(identifier);
                if (data !== null) {
                    data.lastSentPacket = ptr;
                }
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
        events.packetRaw(MinecraftPacketIds.SetLocalPlayerAsInitialized).on((ptr, size) => {
            ptr.move(1); // ignore packet ID, we already know what it is
            var wrapper = new SetLocalPlayerAsInitializedWrapper();
            wrapper.entityRuntimeId = ptr.readVarUint();
            inboundWrapper = wrapper;
        });

        events.packetSendRaw(MinecraftPacketIds.SetActorMotion).on((ptr, size) => {
            ptr.move(1); // ignore packet ID, we already know what it is
            var wrapper = new SetActorMotionWrapper();
            wrapper.entityRuntimeId = ptr.readVarUint();
            wrapper.motion = Vec3.create(ptr.readFloat32(), ptr.readFloat32(), ptr.readFloat32());
            outboundWrapper = wrapper;
        });
        events.packetSendRaw(MinecraftPacketIds.LevelChunk).on((ptr, size) => {
            ptr.move(1); // ignore packet ID, we already know what it is
            var wrapper = new LevelChunkWrapper();
            wrapper.chunkX = ptr.readVarInt();
            wrapper.chunkZ = ptr.readVarInt();
            outboundWrapper = wrapper;
        });

        // Order: packetSend -> packetSendRaw
        for (i = 1; i <= 255; i++) {
            events.packetSendRaw(i).on((ptr: NativePointer, size: number) => {
                if (target !== null) {
                    outboundPacketHandleCallable(target);
                }
                target = null;
            });
        }

        events.networkDisconnected.on((identifier: NetworkIdentifier) => {
            var data = DataStorage.INSTANCE.get(identifier);
            if (data !== null) {
                data.isClosed = true;
            }
        });
    }

}