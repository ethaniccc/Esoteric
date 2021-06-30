import { Packet } from "bdsx/bds/packet";
import { MinecraftPacketIds } from "bdsx/bds/packetids";
import { DisconnectPacket, PlayerAuthInputPacket, SetLocalPlayerAsInitializedPacket } from "bdsx/bds/packets";
import { PlayerData } from "../data/PlayerData";
import { InventoryTransactionWrapper } from "../wrappers/Wrappers";

export class InboundExecutor {

    constructor(
        public data: PlayerData
    ) {}

    public async execute(ptr: Packet) {
        if (ptr instanceof PlayerAuthInputPacket) {
            this.data.currentTick++;
        } else if (ptr instanceof SetLocalPlayerAsInitializedPacket) {
            this.data.loggedIn = true;
        } else if (ptr instanceof DisconnectPacket) {
            this.data.isClosed = true;
        } else if (ptr instanceof InventoryTransactionWrapper) {
        }
    }

}