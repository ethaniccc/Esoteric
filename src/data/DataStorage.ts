import { NetworkIdentifier } from "bdsx/bds/networkidentifier";
import { PlayerData } from "./PlayerData";

export class DataStorage {

    public static INSTANCE: DataStorage;

    public dataList: Map<NetworkIdentifier, PlayerData> = new Map();

    public static init() {
        this.INSTANCE = new DataStorage();
    }

    public add(identifier: NetworkIdentifier): PlayerData {
        var data = new PlayerData(identifier);
        this.dataList.set(identifier, data);
        return data;
    }

    public remove(identifier: NetworkIdentifier) {
        this.dataList.delete(identifier);
    }

    public get(identifier: NetworkIdentifier): PlayerData|null {
        return this.dataList.get(identifier) ?? null;
    }

    public getAll(): Map<NetworkIdentifier, PlayerData> {
        return this.dataList;
    }

}