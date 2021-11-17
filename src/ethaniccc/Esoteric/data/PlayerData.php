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
use ethaniccc\Esoteric\check\misc\timer\TimerA;
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
use ethaniccc\Esoteric\utils\world\VirtualWorld;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\player\Player;
use function array_filter;
use function count;
use function microtime;
use function spl_object_hash;

final class PlayerData{

	/** @var Vector3 - A zero vector, duh. */
	public static ?Vector3 $ZERO_VECTOR = null;

	/** @var Player|null */
	public ?Player $player = null;
	/** @var VirtualWorld */
	public VirtualWorld $world;
	/** @var NetworkSession */
	public NetworkSession $session;
	/** @var string - The spl_object_hash identifier of the player. */
	public string $hash;
	/** @var string - Identifier used in network interface */
	public string $networkIdentifier;
	/** @var int - The current protocol of the player. */
	public int $protocol = ProtocolInfo::CURRENT_PROTOCOL;
	/** @var bool - Boolean value for if the player is logged in. */
	public bool $loggedIn = false;
	/** @var bool - The boolean value for if the player has alerts enabled. This will always be false for players without alert permissions. */
	public bool $hasAlerts = false;
	/** @var int - The alert cooldown the player has set. */
	public int|float $alertCooldown = 0;
	/** @var float - The last time the player has received an alert message. */
	public float $lastAlertTime;
	/** @var Check[] - An array of checks */
	public array $checks = [];
	/** @var ProcessInbound - A class to process packet data sent by the client. */
	public ProcessInbound $inboundProcessor;
	/** @var ProcessOutbound - A class to process packet data sent by the server. */
	public ProcessOutbound $outboundProcessor;
	/** @var ProcessTick - A class to execute every tick. Mainly will be used for NetworkStackLatency timeouts, and */
	public ProcessTick $tickProcessor;
	/** @var LocationMap */
	public LocationMap $entityLocationMap;
	/** @var bool */
	public bool $isMobile = false;
	/** @var int */
	public int $latency = 0;
	/** @var int - ID of the current target entity */
	public int $target = -1;
	/** @var int - ID of the last target entity */
	public int $lastTarget = -1;
	/** @var int - The tick when the player attacked. */
	public int $attackTick = -1;
	/** @var Vector3 - Attack position of the player */
	public Vector3 $attackPos;
	/** @var EffectData[] */
	public array $effects = [];
	/** @var int */
	public int $currentTick = 0;
	/** @var Vector3[] */
	public array $packetDeltas = [];
	/** @var int */
	public int $ticksPerSecond = 0;
	/** @var Vector3 - The current and previous locations of the player */
	public Vector3 $lastOnGroundLocation;
	public Vector3 $lastLocation;
	public Vector3 $currentLocation;
	/** @var Vector3 - Movement deltas of the player */
	public Vector3 $lastMoveDelta;
	public Vector3 $currentMoveDelta;
	/** @var float - Rotation values of the player */
	public float $previousPitch = 0.0;
	public float $currentPitch = 0.0;
	public float $previousYaw = 0.0;
	public float $currentYaw = 0.0;
	/** @var float - Rotation deltas of the player */
	public float $lastPitchDelta = 0.0;
	public float $currentPitchDelta = 0.0;
	public float $lastYawDelta = 0.0;
	public float $currentYawDelta = 0.0;
	/** @var bool - The boolean value for if the player is on the ground. The client on-ground value is used for this. */
	public bool $onGround = true;
	/** @var bool - An expected value for the client's on ground. */
	public bool $expectedOnGround = true;
	/** @var int */
	public int $offGroundTicks = 0;
	public int $onGroundTicks = 0;
	/** @var AABB */
	public AABB $boundingBox;
	/** @var Vector3 */
	public Vector3 $directionVector;
	/** @var int - Ticks since the player has taken motion. */
	public int $ticksSinceMotion = 0;
	/** @var Vector3 */
	public Vector3 $motion;
	/** @var bool */
	public bool $hasBlockAbove = false;
	public bool $isCollidedHorizontally = false;
	public bool $isCollidedVertically = false;
	/** @var int */
	public int $ticksSinceInClimbable = 0;
	public int $ticksSinceInCobweb = 0;
	public int $ticksSinceInLiquid = 0;
	/** @var int - Movements passed since the user teleported. */
	public int $ticksSinceTeleport = 0;
	/** @var bool - Boolean value for if the player is in the void. */
	public bool $isInVoid = false;
	/** @var bool */
	public bool $teleported = false;
	/** @var int - The amount of movements that have passed since the player has disabled flight. */
	public int $ticksSinceFlight = 0;
	/** @var bool - Boolean value for if the player is flying. */
	public bool $isFlying = false;
	/** @var int - Movements that have passed since the user has jumped. */
	public int $ticksSinceJump = 0;
	/** @var bool */
	public bool $hasMovementSuppressed = false;
	/** @var bool - Boolean value for if the player is in a chunk they've received */
	public bool $inLoadedChunk = false;
	/** @var Vector3 - Position sent in NetworkChunkPublisherUpdatePacket */
	public Vector3 $chunkSendPosition;
	/** @var bool */
	public bool $immobile = false;
	/** @var Block[] */
	public array $blocksBelow = [];
	/** @var Block[] */
	public array $lastBlocksBelow = [];
	/** @var bool */
	public bool $canPlaceBlocks = true;
	/** @var float */
	public float $hitboxHeight = 0.0;
	public float $hitboxWidth = 0.0;
	/** @var bool */
	public bool $isAlive = true;
	/** @var int - Amount of client ticks that have passed since the player has spawned. */
	public int $ticksSinceSpawn = 0;
	/** @var bool */
	public bool $isGliding = false;
	/** @var int */
	public int $ticksSinceGlide = 0;
	/** @var int - Device OS of the player */
	public int $playerOS = DeviceOS::UNKNOWN;
	/** @var int - Current gamemode of the player. */
	public int $gamemode = 0;
	public bool $isSprinting = false;
	public float $movementSpeed = 0.1;
	public float $jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;
	public float $jumpMovementFactor = MovementConstants::JUMP_MOVE_NORMAL;
	public float $moveStrafe = 0.0;
	public float $moveForward = 0.0;
	/** @var int[] */
	public array $clickSamples = [];
	/** @var bool - Boolean value for if autoclicker checks should run. */
	public bool $runClickChecks = false;
	/** @var float - Statistical data for autoclicker checks. */
	public float $variance = 0.0;
	public float $outliers = 0.0;
	public float $deviation = 0.0;
	public float $skewness = 0.0;
	public float $kurtosis = 0.0;
	public float $cps = 0.0;
	/** @var int - Last tick the client clicked. */
	public int $lastClickTick = 0;
	/** @var bool */
	public bool $isDataClosed = false;
	/** @var Block|null */
	public ?Block $blockBroken;
	/** @var bool */
	public bool $isFullKeyboardGameplay = true;
	/** @var int[] */
	private array $ticks = [];


	public function __construct(NetworkSession $session){
		if(self::$ZERO_VECTOR === null){
			self::$ZERO_VECTOR = new Vector3(0, 0, 0);
		}
		$this->hash = spl_object_hash($session);
		$this->networkIdentifier = "{$session->getIp()} {$session->getPort()}";
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

		$this->world = new VirtualWorld();

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
			new TimerA(),# Timer checks
		];
	}

	public function tick() : void{
		$this->currentTick++;
		$this->entityLocationMap->executeTick();
		$currentTime = microtime(true);
		$this->ticks = array_filter($this->ticks, static function(float $time) use ($currentTime) : bool{
			return $currentTime - $time < 1;
		});
		$this->ticksPerSecond = count($this->ticks);
		$this->ticks[] = $currentTime;
	}

}