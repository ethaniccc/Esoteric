import { Vector3 } from "../utils/math/Vector3";
import { Packet } from "bdsx/bds/packet";
import { PlayerAuthInputPacket, SetLocalPlayerAsInitializedPacket } from "bdsx/bds/packets";
import { PlayerData } from "../data/PlayerData";
import { NetworkStackLatencyWrapper, SetLocalPlayerAsInitializedWrapper } from "../wrappers/Wrappers";
import { LevelUtils } from "../utils/level/LevelUtils";

export class InboundExecutor {

    constructor(
        public data: PlayerData
    ) {}

    public execute(ptr: Packet) {
        if (ptr instanceof PlayerAuthInputPacket) {
            if (!this.data.loggedIn) {
                return;
            }
            var location = new Vector3(
                ptr.pos.x,
                ptr.pos.y - 1.62,
                ptr.pos.z
            );

            var chunkHash = LevelUtils.chunkHash(location.x >> 4, location.z >> 4);
            this.data.inLoadedChunk = this.data.knownChunks[chunkHash] !== undefined;

            this.data.lastPosition = this.data.currentPosition.clone();
            this.data.currentPosition = location;
            this.data.lastMovement = this.data.currentMovement.clone();
            this.data.currentMovement = this.data.currentPosition.clone().subtractVector(this.data.lastPosition);
            
            this.data.lastYaw = this.data.currentYaw;
            this.data.currentYaw = ptr.yaw;
            this.data.lastPitch = this.data.currentPitch;
            this.data.currentPitch = ptr.pitch;

            this.data.tick();
        } else if (ptr instanceof SetLocalPlayerAsInitializedWrapper) {
            this.data.loggedIn = true;
            this.data.entityRuntimeId = ptr.entityRuntimeId;
            var actor = this.data.identifier.getActor();
            if (actor !== null) {
                this.data.actor = actor;
            }
        } else if (ptr instanceof NetworkStackLatencyWrapper) {
            this.data.networkStackLatencyHandler.handle(ptr.timestamp);
        }
    }

}