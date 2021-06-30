import { NetworkIdentifier } from "bdsx/bds/networkidentifier";
import { Packet } from "bdsx/bds/packet";
import { MinecraftPacketIds } from "bdsx/bds/packetids";
import { PacketIdToType, TextPacket } from "bdsx/bds/packets";
import { NativePointer } from "bdsx/core";
import { log, sleep } from "..";
import { InboundExecutor } from "../processing/InboundProcessor";
import { OutboundExecutor } from "../processing/OutboundProcessor";

export class PlayerData {

    public loggedIn: boolean = false;
    public isClosed: boolean = false;
    public currentTick: number = 0;

    public inboundExecutor: InboundExecutor = new InboundExecutor(this);
    public outboundExecutor: OutboundExecutor = new OutboundExecutor(this);

    public packetInboundQueue: Array<Packet> = [];
    public packetOutboundQueue: Array<Packet> = [];

    constructor(
        public identifier: NetworkIdentifier
    ) {
        var executorTask = async () => {
            const tickSpeed = 50;
            while (true) {
                if (this.isClosed) {
                    break;
                }
                var start = Date.now();
                for (var inboundPtr of this.packetInboundQueue) {
                    await this.inboundExecutor.execute(inboundPtr);
                }
                this.packetInboundQueue = [];
                for (var outboundPtr of this.packetOutboundQueue) {
                    await this.outboundExecutor.execute(outboundPtr);
                }
                this.packetOutboundQueue = [];
                var delta = Date.now() - start;
                await(sleep(Math.max(tickSpeed - delta, 0)));
            }
        };
        executorTask();
    }

    public sendMessage(message: string) {
        var packet = TextPacket.create();
        packet.message = message;
        packet.sendTo(this.identifier);
        packet.dispose();
    }

}