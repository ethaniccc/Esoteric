import { Vector3 } from "../utils/math/Vector3";
import { NetworkIdentifier } from "bdsx/bds/networkidentifier";
import { Packet } from "bdsx/bds/packet";
import { TextPacket } from "bdsx/bds/packets";
import { InboundExecutor } from "../processing/InboundProcessor";
import { NetworkStackLatencyHandler } from "../processing/NetworkStackLatencyHandler";
import { OutboundExecutor } from "../processing/OutboundProcessor";
import { ServerPlayer } from "bdsx/bds/player";
import { BlockSource } from "bdsx/bds/block";

export class PlayerData {

    public actor: ServerPlayer;
    public loggedIn: boolean = false;
    public isClosed: boolean = false;
    public currentTick: number = 0;

    public currentPosition: Vector3 = new Vector3();
    public lastPosition: Vector3 = new Vector3();
    public currentMovement: Vector3 = new Vector3();
    public lastMovement: Vector3 = new Vector3();
    
    public currentYaw: number = 0;
    public lastYaw: number = 0;
    public currentPitch: number = 0;
    public lastPitch: number = 0;
    public currentYawDelta: number = 0;
    public lastYawDelta: number = 0;
    public currentPitchDelta: number = 0;
    public lastPitchDelta: number = 0;

    public knownChunks: Array<number> = [];
    public inLoadedChunk: boolean = false;

    public currentMotion: Vector3 = new Vector3();
    public ticksSinceMotion: number = 0;

    public inboundExecutor: InboundExecutor = new InboundExecutor(this);
    public outboundExecutor: OutboundExecutor = new OutboundExecutor(this);
    public networkStackLatencyHandler: NetworkStackLatencyHandler = new NetworkStackLatencyHandler(this);

    public entityRuntimeId: number = -1;

    public lastSentPacket: Packet;
    public esotericPackets: number = 0;

    constructor(
        public identifier: NetworkIdentifier
    ){}

    public tick(): void {
        this.currentTick++;
        this.ticksSinceMotion++;
    }

    public sendMessage(message: string): void {
        var packet = TextPacket.create();
        packet.message = message;
        packet.sendTo(this.identifier);
        packet.dispose();
    }

    public getRegion(): BlockSource{
        return this.actor.getRegion();
    }

}