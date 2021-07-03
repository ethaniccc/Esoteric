<p align="center">
  <img width="300" height="300" src="https://media.discordapp.net/attachments/727159224320131133/826094659000205322/Esoteric_11A13E3.gif?width=300&height=300">
</p>

<p align="center"><b><font size="+16">Esoteric</font></b></p>

Esoteric is another anti-cheat made for PocketMine-MP. Compared to Mockingbird, this has more checks
I'm deciding to not disclose to the public.
 
## Supported Versions

The versions this anti-cheat currently supports are 1.16.220.

**Protocol List:**
- 437
- 440
- 441

## Planned Features

- [x] Banwaves - for the memes.
- [x] Discord webhook alerts.
- [x] Commands to edit alert delay, turn on/off alerts, and get player logs.
- [ ] Create a functional exempt list for each detection.

## Detections

These are a list of the current detections Esoteric PvP-Optimized has, along with descriptions of those checks.

- **AutoClicker**
  - A: Checks if the player's CPS exceeds a certain threshold.
  - B: Checks if the player has duplicated click statistics.
- **Aim**
  - A: Checks if the player's headYaw does not correlate with the player's yaw.
  - B: Checks if the player's yaw movement is rounded.
- **KillAura**
  - A: Checks if the player is swinging their arm whilst attacking.
  - B: Checks if the player is hitting too many entities in an instance.
- **Range**
  - A: Checks if the player's range goes beyond a certain threshold.
- **Velocity**
  - A: Checks if the player takes less vertical knockback than normal.
  - B: Checks if the player takes less horizontal knockback than normal. This check is currently unusable.
- **EditionFaker**
  - A: Checks if the player's TitleID does not match the given OS in the Login packet.
  
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