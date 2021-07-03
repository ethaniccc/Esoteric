import { Packet } from "bdsx/bds/packet";
import { PlayerData } from "../data/PlayerData";
import { NetworkStackLatencyWrapper } from "../wrappers/Wrappers";

export class NetworkStackLatencyHandler {

    constructor(
        public data: PlayerData
    ){}

    public list: Map<number, () => void> = new Map();
    public sandwhichReceived: Map<number, number> = new Map();
    public currentTimestamp: number = 0;

    /**
     * @param ptr 
     * @param callable 
     * Uses the "sandwich method" to account for an edge-case where the NetworkStackLatency packet
     * and the target packet are received (and processed) in different client ticks.
     * The order:
     * ============
     * Original packet (already sending)
     * NetworkStackLatencyPacket (to send)
     * Original packet CLONE (to send)
     * NetworkStackLatencyPacket v2 (to send)
     * ============
     * If the player has a decent connection, both the original packet and the original packet clone
     * will be received on the same client tick and therefore no difference would be made.
     */
    public sandwich(ptr: Packet, callable: () => void): void {
        this.data.esotericPackets = 3;
        var timestamp = this.increment();
        this.list.set(timestamp, callable);
        this.sandwhichReceived.set(timestamp, 0);

        var var1 = NetworkStackLatencyWrapper.create();
        var1.timestamp = timestamp;
        var1.needsResponse = true;
        var1.sendTo(this.data.identifier);
        var1.dispose();

        ptr.sendTo(this.data.identifier);

        var var2 = NetworkStackLatencyWrapper.create();
        var2.timestamp = timestamp;
        var2.needsResponse = true;
        var2.sendTo(this.data.identifier);
        var2.dispose();
    }

    public send(callable: () => void): void {
        var timestamp1 = this.increment();
        this.list.set(timestamp1, callable);
        var var1 = NetworkStackLatencyWrapper.create();
        var1.timestamp = timestamp1;
        var1.needsResponse = true;
        var1.sendTo(this.data.identifier);
        var1.dispose();
    }

    public handle(timestamp: number): void {
        var current = this.sandwhichReceived.get(timestamp);
        if (current !== undefined) {
            if (current + 1 === 2) { // TODO: Find out why 3 packets are sent to the client - there should only be 2.
                var callable = this.list.get(timestamp);
                if (callable !== undefined) {
                    callable();
                }
                this.list.delete(timestamp);
                this.sandwhichReceived.delete(timestamp);
            } else {
                this.sandwhichReceived.set(timestamp, current + 1);
            }
        } else {
            var callable = this.list.get(timestamp);
            if (callable !== undefined) {
                callable();
            }
            this.list.delete(timestamp);
        }
    }

    public increment(): number {
        this.currentTimestamp += 1000;
        if (this.currentTimestamp % 1000000 === 0) {
            this.currentTimestamp = 1000;
        }
        return this.currentTimestamp;
    }

}