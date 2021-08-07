<p align="center">
  <img width="300" height="300" src="https://media.discordapp.net/attachments/727159224320131133/826094659000205322/Esoteric_11A13E3.gif?width=300&height=300">
</p>

<p align="center"><b><font size="+16">Esoteric</font></b></p>

Esoteric is another anti-cheat made for PocketMine-MP. Compared to Mockingbird, this has more checks
I'm deciding to not disclose to the public.

**NOTICE: Esoteric is now currently in an experimental state - performance may degrade. 
Until this notice is removed, please refrain from using future Esoteric versions on big production servers.**
 
## Supported Versions
- 431 (1.16.220)
- 440 (1.17.0)
- 448 (1.17.10)

## Planned Features

- [x] Banwaves - for the memes.
- [x] Discord webhook alerts.
- [x] Commands to edit alert delay, turn on/off alerts, and get player logs.
- [x] Create a functional exempt list

## Detections

These are a list of the current detections Esoteric has, along with descriptions of those checks.

- **Autoclicker**
  - A: Checks if the player's CPS exceeds a certain threshold.
  - B: Checks if the player has duplicated click statistics
- **Aim**
  - A: Checks if the player's headYaw does not correlate with the player's yaw.
  - B: Checks if the player's yaw movement is rounded.
- **KillAura**
  - A: Checks if the player is swinging their arm whilst attacking.
  - B: Checks if the player is hitting too many entities in an instance.
- **Range**
  - A: Checks if the player's range goes beyond a certain threshold.
- **Fly**
  - A: Estimates the next Y movement of the player. This check detects basic flies.
  - B: Checks if the current Y movement of the player is near equal to the Y movement of the player.
  - C: Checks if the user is jumping on the air.
- **Motion**
  - A: Checks for impossible upward motion. If there is no probable way you are able to go up, this check flags.
    Moreover, this also flags HighJump at a certain threshold along with velocity modifiers > 107%.
  - B: Checks if the player is following Minecraft's friction rules in the air. This check can flag Bhop, and
    some flies.
  - C: Checks if the player is following Minecraft's friction rules while on the ground. This check is surprisingly very effective
    and can detect a variety of speeds on the ground. The idea behind this check is that your current speed multiplied by your friction
    cannot be greater than your previous speed.
  - D: Checks if the player's movement is valid while gliding.
- **Phase**
  - A: Checks if the player makes an invalid move inside a block.
- **Velocity**
  - A: Checks if the player takes less vertical knockback than normal.
  - B: Checks if the player takes less horizontal knockback than normal. This check is currently unusable.
- **EditionFaker**
  - A: Checks if the player's TitleID does not match the given OS in the Login packet.
- **Packets**
  - A: Checks if the player's pitch goes beyond a certain threshold
  - B: Checks if the player is sending the wrong movement packet too frequently
  - C: Checks if the player is jumping without holding their specified jump button. This also checks if their jump delay is invalid.
- **Nuker**
  - A: Checks if the player is breaking blocks too quickly.
- **Timer**
  - A: Checks if the player sends movement packets faster than usual over a period of time.
  
## Permission List
```
ac
|
-> ac.alerts
-> ac.bypass
-> ac.command
   |
   -> ac.command.help
   -> ac.command.logs
   -> ac.command.delay
   -> ac.command.banwave
   -> ac.command.timings
```
