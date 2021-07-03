import { MinecraftPacketIds } from "bdsx/bds/packetids";
import { PacketIdToType } from "bdsx/bds/packets";
import { events } from "bdsx/event";
import { DataStorage } from "./src/data/DataStorage";
import { PacketListener } from "./src/listener/PacketListener";
import { NetworkStackLatencyWrapper } from "./src/wrappers/Wrappers";

events.serverOpen.on(() => {
    PacketListener.init();
    DataStorage.init();

    // Override some packets if needed
    PacketIdToType[MinecraftPacketIds.NetworkStackLatency] = NetworkStackLatencyWrapper;
    PacketIdToType[MinecraftPacketIds.NetworkStackLatency].ID = MinecraftPacketIds.NetworkStackLatency;

    log("Esoteric has been enabled");
    system = server.registerSystem(0, 0);
});

events.serverClose.on(() => {
    log("Esoteric is disabling");
});

export function sleep(time: number) {
    return new Promise(res => setTimeout(res, time));
}

export function log(message: any) {
    console.log("<Esoteric> " + message);
}

export const esotericPath: string = process.cwd() + "/../esoteric/resources";
export var system: IVanillaServerSystem;