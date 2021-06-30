import { MinecraftPacketIds } from "bdsx/bds/packetids";
import { PacketIdToType } from "bdsx/bds/packets";
import { events } from "bdsx/event";
import { DataStorage } from "./data/DataStorage";
import { PacketListener } from "./PacketListener";
import { InventoryTransactionWrapper } from "./wrappers/Wrappers";

events.serverOpen.on(()=>{
    PacketListener.init();
    DataStorage.init();
    log("Esoteric has been enabled");
});

events.serverClose.on(()=>{
    log("Esoteric is disabling");
});

export function sleep(time: number) {
    return new Promise(res => setTimeout(res, time));
}

export function log(message: any) {
    console.log("<Esoteric> " + message);
}