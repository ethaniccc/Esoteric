<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\check\combat\aim\AimA;
use ethaniccc\Esoteric\check\combat\aim\AimB;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerA;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerB;
use ethaniccc\Esoteric\check\combat\autoclicker\AutoClickerC;
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
use ethaniccc\Esoteric\check\movement\motion\MotionD;
use ethaniccc\Esoteric\check\movement\phase\PhaseA;
use ethaniccc\Esoteric\check\movement\velocity\VelocityA;
use ethaniccc\Esoteric\check\movement\velocity\VelocityB;
use ethaniccc\Esoteric\data\process\ProcessInbound;
use ethaniccc\Esoteric\data\process\ProcessOutbound;
use ethaniccc\Esoteric\data\process\ProcessTick;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use ethaniccc\Esoteric\data\sub\location\LocationMap;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\utils\AABB;
use ethaniccc\Esoteric\utils\EvictingList;
use ethaniccc\Esoteric\utils\handler\DebugHandler;
use ethaniccc\Esoteric\utils\world\VirtualWorld;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\Player;
use function array_filter;
use function array_keys;
use function count;
use function microtime;
use function spl_object_hash;

final class PlayerData {

	public static ?Vector3 $ZERO_VECTOR = null;

	public Player $player;
	public string $hash;
	/** @var DebugHandler[] */
	public array $debugHandlers = [];
	/** @var EvictingList */
	public EvictingList $movements;
	public string $networkIdentifier;
	public int $protocol = ProtocolInfo::CURRENT_PROTOCOL;
	public bool $loggedIn = false;
	public bool $hasAlerts = false;
	public int $alertCooldown = 0;
	public float $lastAlertTime;
	/** @var Check[] - An array of checks */
	public array $checks = [];
	/** @var ProcessInbound - A class to process packet data sent by the client. */
	public ProcessInbound $inboundProcessor;
	/** @var ProcessOutbound - A class to process packet data sent by the server. */
	public ProcessOutbound $outboundProcessor;
	/** @var ProcessTick - A class to execute every tick. Mainly will be used for NetworkStackLatency timeouts, and */
	public ProcessTick $tickProcessor;
	public LocationMap $entityLocationMap;
	public bool $isMobile = false;
	public int $gameLatency = 0;
	public int $networkLatency = 0;
	public int $target = -1;
	public int $lastTarget = -1;
	public int $attackTick = -1;
	public Vector3 $attackPos;
	/** @var EffectData[] */
	public array $effects = [];
	public int $currentTick;
	/** @var Vector3[] */
	public array $packetDeltas = [];
	public int $ticksPerSecond = 0;
	public Vector3 $currentLocation, $lastLocation, $lastOnGroundLocation;
	public float $currentMoveDelta, $lastMoveDelta;
	public float $currentYaw = 0.0, $previousYaw = 0.0, $currentPitch = 0.0, $previousPitch = 0.0;
	public float $currentYawDelta = 0.0, $lastYawDelta = 0.0, $currentPitchDelta = 0.0, $lastPitchDelta = 0.0;
	public bool $onGround = true;
	public bool $expectedOnGround = true;
	public int $onGroundTicks = 0, $offGroundTicks = 0;
	public AABB $boundingBox;
	public AABB $lastBoundingBox;
	public Vector3 $directionVector;
	public int $ticksSinceMotion = 0;
	public Vector3 $motion;
	public bool $isCollidedVertically = false, $isCollidedHorizontally = false, $hasBlockAbove = false;
	public int $ticksSinceInLiquid = 0, $ticksSinceInCobweb = 0, $ticksSinceInClimbable = 0;
	public int $ticksSinceTeleport = 0;
	public bool $isGliding = false;
	public int $ticksSinceGlide = 0;
	public bool $isInVoid = false;
	public float $gravity = MovementConstants::NORMAL_GRAVITY;
	public float $ySize = 0;
	public bool $teleported = false;
	public int $ticksSinceFlight = 0;
	public bool $isFlying = false;
	public bool $isClipping = false;
	public int $ticksSinceJump = 0;
	public bool $hasMovementSuppressed = false;
	public bool $inLoadedChunk = false;
	public Vector3 $chunkSendPosition;
	public bool $immobile = false;
	/** @var Block[] */
	public array $blocksBelow = [];
	/** @var Block[] */
	public array $lastBlocksBelow = [];
	public bool $canPlaceBlocks = true;
	public float $hitboxWidth = 0.0, $hitboxHeight = 0.0;
	public bool $isAlive = true;
	public int $ticksSinceSpawn = 0;
	public int $playerOS = DeviceOS::UNKNOWN;
	public int $gamemode = 0;
	public bool $isSprinting = false;
	public bool $isSneaking = false;
	public float $movementSpeed = 0.1;
	public float $jumpVelocity = MovementConstants::DEFAULT_JUMP_MOTION;
	public float $jumpMovementFactor = MovementConstants::JUMP_MOVE_NORMAL;
	public float $moveForward = 0.0, $moveStrafe = 0.0;
	/** @var int[] */
	public array $clickSamples = [];
	public bool $runClickChecks = false;
	/** @var float - Statistical data for autoclicker checks. */
	public float $cps = 0.0, $kurtosis = 0.0, $skewness = 0.0, $deviation = 0.0, $outliers = 0.0, $variance = 0.0;
	public int $lastClickTick = 0;
	public bool $isDataClosed = false;
	public ?Block $blockBroken = null;
	public bool $isFullKeyboardGameplay = true;
	public VirtualWorld $world;
	/** @var int[] */
	private array $ticks = [];

	public function __construct(Player $player) {
		if (self::$ZERO_VECTOR === null) {
			self::$ZERO_VECTOR = new Vector3(0, 0, 0);
		}
		$this->currentTick = $player->getServer()->getTick();
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

		$this->alertCooldown = 0;
		$this->lastAlertTime = microtime(true);

		$this->world = new VirtualWorld();
		$this->movements = new EvictingList(20);

		$this->checks = [
			new AimA(), new AimB(), # Aim checks
			new AutoClickerA(), new AutoClickerB(), new AutoClickerC(), # Autoclicker checks
			new KillAuraA(), new KillAuraB(), # Killaura checks
			new RangeA(), # Range checks
			new FlyA(), new FlyB(), new FlyC(), # Fly checks
			new MotionA(), new MotionB(), new MotionC(), new MotionD(), # Motion checks
			new VelocityA(), new VelocityB(), # Velocity checks
			new PhaseA(), # Phase checks
			new PacketsA(), new PacketsB(), new PacketsC(), # Packet checks
			new EditionFakerA(), # EditionFaker checks
			new NukerA(), # Nuker checks
			new TimerA(),  # Timer checks
		];

		foreach ($this->checks as $check) {
			$handler = new DebugHandler($check->name . " ({$check->subType})");
			$this->debugHandlers[$handler->getName()] = $handler;
		}
	}

	public function tick(): void {
		$this->currentTick++;
		$this->entityLocationMap->executeTick($this);
		$currentTime = microtime(true);
		$this->ticks = array_filter($this->ticks, static function (float $time) use ($currentTime): bool {
			return $currentTime - $time < 1;
		});
		$this->ticksPerSecond = count($this->ticks);
		$this->ticks[] = $currentTime;
	}

	public function destroy(): void {
		$keys = array_keys($this->world->getAllChunks());
		foreach ($keys as $key) {
			$this->world->removeChunkByHash($key);
		}
		$keys = array_keys($this->checks);
		foreach ($keys as $key) {
			$this->checks[$key] = null;
			unset($this->checks[$key]);
		}
		unset($this->player);
		unset($this->inboundProcessor);
		unset($this->outboundProcessor);
		unset($this->tickProcessor);
		unset($this->entityLocationMap);
	}

}