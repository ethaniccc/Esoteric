<p align="center">
  <img width="300" height="300" src="https://media.discordapp.net/attachments/727159224320131133/826094659000205322/Esoteric_11A13E3.gif?width=300&height=300">
</p>

<p align="center"><b><font size="+16">Esoteric</font></b></p>

Esoteric is another anti-cheat made for PocketMine-MP. Compared to Mockingbird, this has more checks
I'm deciding to not disclose to the public.

## Supported Versions

The versions this anti-cheat currently supports are 1.16.100, 1.16.201, 1.16.210, 1.16.220

**Protocol List:**

- 419
- 422
- 428
- 431
- 
## Planned Features

- [ ] Banwaves - for the memes.
- [ ] Discord webhook alerts.
- [ ] Commands to edit alert delay, turn on/off alerts, and get player logs.
- [ ] Create a functional exempt list for each detection.

## Detections

These are a list of the current detections Esoteric has, along with descriptions of those checks.

- **Autoclicker**
  - A: Checks if the player's CPS exceeds a certain threshold.
- **Aim**
  - A: Checks if the player's headYaw does not correlate with the player's yaw.
  - B: Checks if the player's yaw movement is rounded.
- **KillAura**
  - A: Checks if the player is swinging their arm whilst attacking.
- **Range**
  - A: Checks if the player's range goes beyond a certain threshold. Devices which have the capability to switch to touch-screen easily are exempted from this check.
  - B: Checks if the player is looking at the entity it's attacking.
- **Fly**
  - A: Estimates the next Y movement of the player. This check detects basic flies.
  - B: Checks if the current Y movement of the player is near equal to the Y movement of the player.
  - C: Checks if the user is jumping on the air.
- **Motion**
  - A: Checks for impossible upward motion. If there is no probable way you are able to go up, this check flags.
    Moreover, this also flags HighJump at a certain threshold.
  - B: Checks if the player is following Minecraft's friction rules in the air. This check can flag Bhop, and
    some flies.
  - C: Checks if the player is following Minecraft's friction rules while on the ground. This check is surprisingly very effective
    and can detect a variety of speeds on the ground. The idea behind this check is that your current speed multiplied by your friction
    cannot be greater than your previous speed.
  - D (**Exp**): Checks if the player's XZ velocity while jumping exceeds a certain threshold. This mainly blocks hacks such as "LongJump".
- **Velocity**
  - A: Checks if the player takes less vertical knockback than normal.
  - B: Checks if the player takes less horizontal knockback than normal. This check is currently unusable.
- **GroundSpoof**
  - A: This checks if the player says that they're on the ground while not having any solid blocks around them.
- **EditionFaker**
  - A: Checks if the player's TitleID does not match the given OS in the Login packet.
