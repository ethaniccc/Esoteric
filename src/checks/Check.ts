import { PlayerData } from "../data/PlayerData";

export abstract class Check {

    protected subChecks: Map<string, SubCheck> = new Map();

    constructor(
        public name: string,
        public description: string,
        public data: PlayerData
    ){}

    public execute(): void {
        this.subChecks.forEach((check: SubCheck) => {
            check.run();
        });
    }

}

export abstract class SubCheck {

    constructor(
        public parent: Check,
        public type: string,
        public description: string,
        public data: PlayerData
    ){}
    
    public violations: number = 0;
    public buffer: number = 0;

    public abstract run(): void;

    public flag(suppress: boolean = false): void {
        if (suppress) {
            // TODO: Reverting movement.
        }
        ++this.violations;
        this.data.sendMessage("[" + this.parent.name + " (" + this.type + ")] " + "vl=" + this.violations);
    }

    public reward(amount: number = 0.01): void {
        this.violations = Math.max(this.violations - amount, 0);
    }

}