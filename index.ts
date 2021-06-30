import { events } from "bdsx/event";
import { DataStorage } from "./src/data/DataStorage";
import { PacketListener } from "./src/listener/PacketListener";

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