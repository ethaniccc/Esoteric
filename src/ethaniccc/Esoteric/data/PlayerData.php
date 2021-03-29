<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\check\combat\aimassist\AimAssistA;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerA;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerB;
use ethaniccc\Esoteric\check\combat\range\RangeA;
use ethaniccc\Esoteric\check\movement\fly\FlyA;
use ethaniccc\Esoteric\check\movement\fly\FlyB;
use ethaniccc\Esoteric\check\movement\invalid\InvalidMoveA;
use ethaniccc\Esoteric\check\movement\motion\MotionA;
use ethaniccc\Esoteric\check\movement\motion\MotionB;
use ethaniccc\Esoteric\check\movement\motion\MotionC;
use ethaniccc\Esoteric\check\movement\velocity\VelocityA;
use ethaniccc\Esoteric\check\packet\badpacket\BadPacketsA;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use ethaniccc\Esoteric\data\sub\location\LocationMap;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\handle\InboundHandle;
use ethaniccc\Esoteric\handle\OutboundHandle;
use ethaniccc\Esoteric\utils\AABB;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\Player;

final class PlayerData{

    /** @var Player */
    public $player;
    /** @var int - The protocol of the player. */
    public $protocol = ProtocolInfo::CURRENT_PROTOCOL;
    /** @var string - The spl_object_hash of the player, used to prevent multiple calls of spl_object_hash(); */
    public $hash;
    /** @var bool */
    public $hasAlerts = false;
    /** @var Check[] */
    public $checks = [];
    /** @var array - A list of callables to happen at a certain client tick */
    public $await = [];
    /** @var EffectData[] - A list of effects */
    public $effects = [];
    /** @var Vector3 - A zero vector */
    public static $zeroVector;

    public function __construct(Player $player){
        $this->player = $player;
        $this->hash = spl_object_hash($player);
        if(self::$zeroVector === null)
            self::$zeroVector = new Vector3(0, 0, 0);
        $this->currentMoveDelta = clone self::$zeroVector;
        $this->lastMoveDelta = clone self::$zeroVector;
        $this->currentLocation = clone self::$zeroVector;
        $this->lastLocation = clone self::$zeroVector;
        $this->directionVector = clone self::$zeroVector;
        $this->motion = clone self::$zeroVector;
        $this->lastOnGroundLocation = clone self::$zeroVector;
        $this->entityLocationMap = new LocationMap($this);
        $this->inboundHandler = new InboundHandle();
        $this->outboundHandler = new OutboundHandle();
        // checks for the player
        $this->checks = [
            # Combat

            # Autoclicker
            new AutoClickerA(),
            new AutoClickerB(),

            # Range
            new RangeA(),

            # AimAssist
            new AimAssistA(),

            # Movement

            # Velocity
            new VelocityA(),

            # Fly
            new FlyA(),
            new FlyB(),

            # Motion
            new MotionA(),
            new MotionB(),
            new MotionC(),

            # Invalid movement
            new InvalidMoveA(),

            # Bad packets
            new BadPacketsA(),
        ];
    }

    public function await(callable $callable, int $future) : void{
        $future = max($future, 1);
        if(!isset($this->await[$this->currentTick + $future])) $this->await[$this->currentTick + $future] = [];
        $this->await[$this->currentTick + $future][] = $callable;
    }

    /** @var bool - Boolean value for if the player is logged into the server. */
    public $loggedIn = false;
    /** @var InboundHandle - A class that handles packets sent by the client for information */
    public $inboundHandler;
    /** @var OutboundHandle */
    public $outboundHandler;

    /** Movement data */

    /** @var Vector3 - The current and previous locations of the player */
    public $currentLocation, $lastLocation, $lastOnGroundLocation;
    /** @var Vector3 - Movement deltas of the player */
    public $currentMoveDelta, $lastMoveDelta;
    /** @var float - Rotation values of the player */
    public $currentYaw = 0.0, $previousYaw = 0.0, $currentPitch = 0.0, $previousPitch = 0.0;
    /** @var float - Rotation deltas of the player */
    public $currentYawDelta = 0.0, $lastYawDelta = 0.0, $currentPitchDelta = 0.0, $lastPitchDelta = 0.0;
    /** @var bool - The boolean value for if the player is on the ground */
    public $onGround = true;
    /** @var bool - Boolean value for if the user is colliding with the ground. This is basically a more lenient version of onGround */
    public $groundCollision = true;
    /** @var int */
    public $onGroundTicks = 0, $offGroundTicks = 0;
    /** @var AABB */
    public $boundingBox;
    /** @var Vector3 */
    public $directionVector;
    /** @var Vector3 */
    public $motion;
    /** @var bool */
    public $isCollidedVertically = false, $isCollidedHorizontally = false, $hasBlockAbove = false;
    /** @var int */
    public $ticksSinceInLiquid = 0, $ticksSinceInCobweb = 0, $ticksSinceInClimbable = 0;
    /** @var Vector3|null */
    public $teleportPos;
    /** @var bool */
    public $awaitingTeleport = false;
    /** @var bool */
    public $hasMovementSuppressed = false;

    /** Clicking data */

    /** @var float - The cps of the player */
    public $cps = 0.0;
    /** @var float - Statistical data of clicks */
    public $kurtosis = 0.0, $skewness = 0.0, $deviation = 0.0, $outliers = 0.0, $variance = 0.0;
    /** @var bool - Boolean value for if auto-clicker checks should run. */
    public $runClickChecks = false;
    /** @var int[] - An array of client-tick samples between clicks */
    public $clickSamples = [];
    /** @var int - The last tick the player clicked. */
    public $lastClickTick = 0;

    /** Tick data */

    /** @var int - The current tick of the player. */
    public $currentTick = 0;

    /** Combat data */

    /** @var Entity|null */
    public $targetEntity, $lastTargetEntity;
    /** @var LocationMap */
    public $entityLocationMap;

    /** Client data */

    /** @var bool */
    public $isTouch;
    /** @var int */
    public $inputMode;

    /** Timed data */

    public $timeSinceAttack = 0;
    public $timeSinceMotion = 0;
    public $timeSinceJump = 0;
    public $timeSinceTeleport = 0;
    public $timeSinceJoin = 0;
    public $timeSinceFlight = 0;

    /** Extra movement data */

    public $isSprinting = false;
    public $isFlying = false;
    public $movementSpeed = 0.1;
    public $moveForward = 0.0, $moveStrafe = 0.0, $pressedKeys = [];
    public $jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;
    public $jumpMovementFactor = MovementConstants::JUMP_MOVE_NORMAL;

}