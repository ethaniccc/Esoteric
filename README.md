# ![idk](https://media.discordapp.net/attachments/727159224320131133/826094659000205322/Esoteric_11A13E3.gif?width=50&height=50) Esoteric
Esoteric is another anti-cheat made for PocketMine-MP. Compared to Mockingbird, this has more checks
I'm deciding to not disclose to the public.

## Supported Versions
The versions this anti-cheat currently supports are 1.16.100, and 1.16.201. There is support for 1.16.210,
but it is currently unstable because of protocol changes that are not necessary (hi Microjang).

**Protocol List:**
- 419
- 422
- 428

## Planned Features
- [ ] Banwaves - for the memes.
- [ ] Discord webhook alerts.
- [ ] Commands to edit alert delay, turn on/off alerts, and get player logs.
- [ ] Create a functional exempt list for each detection.

## Detections
These are a list of the current detections Esoteric has, along with descriptions of those checks.

* **AimAssist**
    - A: Checks for rounded head movement (mainly made for Ascendency's aim-assist)
* **AutoClicker**
    - A: Checks for a high amount of CPS. The CPS limit is editable in the config.
    - B: Checks for statistical data of clicks that seem abnormal (low kurtosis, skewness, and outliers).
* **Range**
    - A: Utilizes Esoteric's LocationMap along with AABB->distanceFromVector() to check for raw player range.
* **Fly**
    - A: Estimates the next Y movement of the player. This check detects basic flies.
    - B: Checks if the current Y movement of the player is near equal to the  Y movement of the player.
* **InvalidMovement**
    - A: Checks if your jump delay was valid. If you're holding your jump key, your jump delay will be 10 ticks.
    If not, your jump delay can be anything. If you're not holding your jump key, but you're jumping, that is also considered invalid and is checked in here.
* **Motion**
    - A: Checks for impossible upward motion. If there is no probable way you are able to go up, this check flags.
    Moreover, this also flags HighJump at a certain threshold.
    - B: Checks if the player is following Minecraft's friction rules in the air. This check can flag Bhop, and
    some flies.
    - C: Checks if the player is following Minecraft's friction rules while on the ground. This check is surprisingly very effective
    and can detect a variety of speeds on the ground.
* **Velocity**
    - A: Checks if the player took less or more vertical knockback than normal. This works 99% of the time.
* **BadPackets**
    - A: Checks if the player is sending the wrong movement packet consistently. When Esoteric modifies the StartGame packet, the player sends
    PlayerAuthInputPacket, as opposed to MovePlayerPacket. The client will only send MovePlayerPacket when landing on the ground.
    A majority of hacks like to abuse sending MovePlayerPacket, which is why this check was put into place.  