<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\check\combat\aim\AimA;
use ethaniccc\Esoteric\check\combat\aim\AimB;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerA;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerB;
use ethaniccc\Esoteric\check\combat\killaura\KillAuraA;
use ethaniccc\Esoteric\check\combat\killaura\KillAuraB;
use ethaniccc\Esoteric\check\combat\range\RangeA;
use ethaniccc\Esoteric\check\misc\editionfaker\EditionFakerA;
use ethaniccc\Esoteric\check\misc\nuker\NukerA;
use ethaniccc\Esoteric\check\misc\packets\PacketsA;
use ethaniccc\Esoteric\check\misc\packets\PacketsB;
use ethaniccc\Esoteric\check\misc\packets\PacketsC;
use ethaniccc\Esoteric\check\movement\fly\FlyA;
use ethaniccc\Esoteric\check\movement\fly\FlyB;
use ethaniccc\Esoteric\check\movement\fly\FlyC;
use ethaniccc\Esoteric\check\movement\motion\MotionA;
use ethaniccc\Esoteric\check\movement\motion\MotionB;
use ethaniccc\Esoteric\check\movement\motion\MotionC;
use ethaniccc\Esoteric\check\movement\velocity\VelocityA;
use ethaniccc\Esoteric\data\process\ProcessInbound;
use ethaniccc\Esoteric\data\process\ProcessOutbound;
use ethaniccc\Esoteric\data\process\ProcessTick;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use ethaniccc\Esoteric\data\sub\location\LocationMap;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\AABB;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\Player;
use function array_filter;
use function count;
use function microtime;
use function spl_object_hash;

final class PlayerData {

	/** @var Vector3 - A zero vector, duh. */
	public static $ZERO_VECTOR;

	/** @var Player */
	public $player;
	/** @var string - The spl_object_hash identifier of the player. */
	public $hash;
	/** @var string - Identifier used in network interface */
	public $networkIdentifier;
	/** @var int - The current protocol of the player. */
	public $protocol = ProtocolInfo::CURRENT_PROTOCOL;
	/** @var bool - Boolean value for if the player is logged in. */
	public $loggedIn = false;
	/** @var bool - The boolean value for if the player has alerts enabled. This will always be false for players without alert permissions. */
	public $hasAlerts = false;
	/** @var int - The alert cooldown the player has set. */
	public $alertCooldown = 0;
	/** @var float - The last time the player has received an alert message. */
	public $lastAlertTime;
	/** @var Check[] - An array of checks */
	public $checks = [];
	/** @var ProcessInbound - A class to process packet data sent by the client. */
	public $inboundProcessor;
	/** @var ProcessOutbound - A class to process packet data sent by the server. */
	public $outboundProcessor;
	/** @var ProcessTick - A class to execute every tick. Mainly will be used for NetworkStackLatency timeouts, and */
	public $tickProcessor;
	/** @var LocationMap */
	public $entityLocationMap;
	/** @var bool */
	public $isMobile = false;
	/** @var int */
	public $latency = 0;
	/** @var int - ID of the current target entity */
	public $target = -1;
	/** @var int - ID of the last target entity */
	public $lastTarget = -1;
	/** @var int - The tick when the player attacked. */
	public $attackTick = -1;
	/** @var Vector3 - Attack position of the player */
	public $attackPos;
	/** @var EffectData[] */
	public $effects = [];
	/** @var int */
	public $currentTick = 0;
	/** @var Vector3[] */
	public $packetDeltas = [];
	/** @var int */
	public $ticksPerSecond = 0;
	/** @var Vector3 - The current and previous locations of the player */
	public $currentLocation, $lastLocation, $lastOnGroundLocation;
	/** @var Vector3 - Movement deltas of the player */
	public $currentMoveDelta, $lastMoveDelta;
	/** @var float - Rotation values of the player */
	public $currentYaw = 0.0, $previousYaw = 0.0, $currentPitch = 0.0, $previousPitch = 0.0;
	/** @var float - Rotation deltas of the player */
	public $currentYawDelta = 0.0, $lastYawDelta = 0.0, $currentPitchDelta = 0.0, $lastPitchDelta = 0.0;
	/** @var bool - The boolean value for if the player is on the ground. The client on-ground value is used for this. */
	public $onGround = true;
	/** @var bool - An expected value for the client's on ground. */
	public $expectedOnGround = true;
	/** @var int */
	public $onGroundTicks = 0, $offGroundTicks = 0;
	/** @var AABB */
	public $boundingBox;
	/** @var Vector3 */
	public $directionVector;
	/** @var int - Ticks since the player has taken motion. */
	public $ticksSinceMotion = 0;
	/** @var Vector3 */
	public $motion;
	/** @var bool */
	public $isCollidedVertically = false, $isCollidedHorizontally = false, $hasBlockAbove = false;
	/** @var int */
	public $ticksSinceInLiquid = 0, $ticksSinceInCobweb = 0, $ticksSinceInClimbable = 0;
	/** @var int - Movements passed since the user teleported. */
	public $ticksSinceTeleport = 0;
	/** @var bool - Boolean value for if the player is in the void. */
	public $isInVoid = false;
	/** @var bool */
	public $teleported = false;
	/** @var int - The amount of movements that have passed since the player has disabled flight. */
	public $ticksSinceFlight = 0;
	/** @var bool - Boolean value for if the player is flying. */
	public $isFlying = false;
	/** @var int - Movements that have passed since the user has jumped. */
	public $ticksSinceJump = 0;
	/** @var bool */
	public $hasMovementSuppressed = false;
	/** @var bool - Boolean value for if the player is in a chunk they've received */
	public $inLoadedChunk = false;
	/** @var Vector3 - Position sent in NetworkChunkPublisherUpdatePacket */
	public $chunkSendPosition;
	/** @var bool */
	public $immobile = false;
	/** @var Block[] */
	public $blocksBelow = [];
	/** @var Block[] */
	public $lastBlocksBelow = [];
	/** @var bool */
	public $canPlaceBlocks = true;
	/** @var float */
	public $hitboxWidth = 0.0, $hitboxHeight = 0.0;
	/** @var bool */
	public $isAlive = true;
	/** @var int - Amount of client ticks that have passed since the player has spawned. */
	public $ticksSinceSpawn = 0;
	/** @var int - Device OS of the player */
	public $playerOS = DeviceOS::UNKNOWN;
	/** @var int - Current gamemode of the player. */
	public $gamemode = 0;
	public $isSprinting = false;
	public $isSneaking = false;
	public $movementSpeed = 0.1;
	public $jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;
	public $jumpMovementFactor = MovementConstants::JUMP_MOVE_NORMAL;
	public $moveForward = 0.0, $moveStrafe = 0.0;
	/** @var int[] */
	public $clickSamples = [];
	/** @var bool - Boolean value for if autoclicker checks should run. */
	public $runClickChecks = false;
	/** @var float - Statistical data for autoclicker checks. */
	public $cps = 0.0, $kurtosis = 0.0, $skewness = 0.0, $deviation = 0.0, $outliers = 0.0, $variance = 0.0;
	/** @var int - Last tick the client clicked. */
	public $lastClickTick = 0;
	/** @var bool */
	public $isClickDataIsValid = true;
	/** @var bool */
	public $isDataClosed = false;
	/** @var Block|null */
	public $blockBroken;
	/** @var bool */
	public $isFullKeyboardGameplay = true;
	/** @var int[] */
	private $ticks = [];


	public function __construct(Player $player) {
		if (self::$ZERO_VECTOR === null) {
			self::$ZERO_VECTOR = new Vector3(0, 0, 0);
		}
		$this->player = $player;
		$this->hash = spl_object_hash($player);
		$this->networkIdentifier = "{$player->getAddress()} {$player->getPort()}";
		$zeroVec = clone self::$ZERO_VECTOR;

		// AIDS START
		$this->currentLocation = $this->lastLocation = $this->currentMoveDelta = $this->lastMoveDelta = $this->lastOnGroundLocation = $this->directionVector = $this->motion = $zeroVec;
		// AIDS END

		$this->inboundProcessor = new ProcessInbound();
		$this->outboundProcessor = new ProcessOutbound();
		$this->tickProcessor = new ProcessTick();

		$this->entityLocationMap = new LocationMap();

		$this->alertCooldown = Esoteric::getInstance()->getSettings()->getAlertCooldown();
		$this->lastAlertTime = microtime(true);

		$this->checks = [new AimA(), new AimB(), # Aim checks
			new AutoClickerA(), new AutoClickerB(), # Autoclicker checks
			new KillAuraA(), new KillAuraB(), # Killaura checks
			new RangeA(), # Range checks
			new FlyA(), new FlyB(), new FlyC(), # Fly checks
			new MotionA(), new MotionB(), new MotionC(), # Motion checks
			new VelocityA(), # Velocity checks
			new PacketsA(), new PacketsB(), new PacketsC(), # Packet checks
			new EditionFakerA(), # EditionFaker checks
			new NukerA(), # Nuker checks
		];
	}

	public function tick(): void {
		$this->currentTick++;
		$this->entityLocationMap->executeTick($this);
		$currentTime = microtime(true);
		$this->ticks = array_filter($this->ticks, function (float $time) use ($currentTime): bool {
			return $currentTime - $time < 1;
		});
		$this->ticksPerSecond = count($this->ticks);
		$this->ticks[] = $currentTime;
	}

}