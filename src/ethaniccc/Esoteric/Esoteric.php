<?php

namespace ethaniccc\Esoteric;

use ethaniccc\Esoteric\command\EsotericCommand;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\PlayerDataManager;
use ethaniccc\Esoteric\listener\Listener;
use ethaniccc\Esoteric\network\RaklibOverride;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use ethaniccc\Esoteric\tasks\TickingTask;
use ethaniccc\Esoteric\thread\LoggerThread;
use ethaniccc\Esoteric\utils\banwave\Banwave;
use Exception;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\BlockToolType;
use pocketmine\block\Cactus;
use pocketmine\block\WoodenFence;
use pocketmine\event\HandlerListManager;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use const PTHREADS_INHERIT_NONE;

final class Esoteric {

	/** @var Esoteric|null */
	private static $instance;

	/** @var bool */
	public $running = false;
	/** @var Plugin - Plugin that initialized Esoteric. */
	public $plugin;
	/** @var Settings */
	public $settings;
	/** @var LoggerThread */
	public $logger;
	/** @var Listener */
	public $listener;
	/** @var PlayerData[] */
	public $hasAlerts = [];
	/** @var PlayerDataManager */
	public $dataManager;
	/** @var string[] */
	public $logCache = [];
	/** @var TickingTask */
	public $tickingTask;
	/** @var Banwave|null */
	public $banwave;
	/** @var EsotericCommand */
	public $command;

	/**
	 * Esoteric constructor.
	 * @param PluginBase $plugin
	 * @param Config|null $config
	 */
	private function __construct(PluginBase $plugin, ?Config $config) {
		$this->plugin = $plugin;
		$this->settings = new Settings($config->getAll());
		$this->logger = new LoggerThread($this->getPlugin()->getDataFolder() . "esoteric.log");
		$this->listener = new Listener();
		$this->dataManager = new PlayerDataManager();
		$this->tickingTask = new TickingTask();
	}

	/**
	 * @param PluginBase $plugin - Plugin to initialize Esoteric.
	 * @param Config $settings - Configuration for Esoteric.
	 * @param bool $start - If Esoteric should start after initialization.
	 * @throws Exception
	 */
	public static function init(PluginBase $plugin, Config $settings, bool $start = false): void {
		if (self::$instance !== null)
			throw new Exception("Esoteric has already been initialized by " . self::$instance->plugin->getName());
		self::$instance = new self($plugin, $settings);
		if ($start)
			self::$instance->start();
	}

	public function start(): void {
		if ($this->running)
			return;

		$this->getPlugin()->getScheduler()->scheduleTask(new ClosureTask(function(): void {
			foreach (Server::getInstance()->getNetwork()->getInterfaces() as $interface) {
				if ($interface instanceof RakLibInterface) {
					Server::getInstance()->getNetwork()->unregisterInterface($interface);
					$interface->shutdown();
					break;
				}
			}

			Server::getInstance()->getNetwork()->registerInterface(new RaklibOverride(Server::getInstance()));
		}));

		BlockFactory::getInstance()->register(new class() extends Cactus {
			public function __construct() {
				parent::__construct(new BlockIdentifier(BlockLegacyIds::CACTUS, 0), "Cactus", new BlockBreakInfo(0.4));
			}
			protected function recalculateCollisionBoxes(): array {
				static $shrinkSize = 1 / 16;
				return [AxisAlignedBB::one()->contract($shrinkSize, 0, $shrinkSize)]; // the shrink can cause issues
			}
		}, true);
		BlockFactory::getInstance()->register(new class() extends WoodenFence {
			public function __construct() {
				parent::__construct(new BlockIdentifier(BlockLegacyIds::FENCE, 7), "Oak Fence [Hack]",  new BlockBreakInfo(2.0, BlockToolType::AXE, 0, 15.0));
			}
		});

		$this->logger->start(PTHREADS_INHERIT_NONE);
		$this->plugin->getServer()->getPluginManager()->registerEvents($this->listener, $this->plugin);
		$this->plugin->getScheduler()->scheduleRepeatingTask($this->tickingTask, 1);
		if ($this->settings->getWaveSettings()["enabled"]) {
			@mkdir($this->getPlugin()->getDataFolder() . "banwaves");
			$count = count(scandir($this->getPlugin()->getDataFolder() . "banwaves")) - 2;
			if ($count === 0) {
				Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask($this->getPlugin()->getDataFolder() . "banwaves/banwave-1.json", function (Banwave $banwave): void {
					$this->banwave = $banwave;
				}));
			} else {
				$filtered = array_filter(scandir($this->getPlugin()->getDataFolder() . "banwaves"), static function (string $file): bool {
					return strtolower(($array = explode(".", $file))[count($array) - 1]) === "json";
				});
				Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask($this->getPlugin()->getDataFolder() . "banwaves/" . $filtered[max(array_keys($filtered))], function (Banwave $banwave): void {
					$this->banwave = $banwave;
				}));
			}
		}
		$this->command = new EsotericCommand();
		Server::getInstance()->getCommandMap()->register($this->plugin->getName(), $this->command);
		PacketPool::getInstance()->registerPacket(new PlayerAuthInputPacket());

		$this->running = true;
	}

	public function getPlugin(): PluginBase {
		return $this->plugin;
	}

	/**
	 * @return Esoteric|null
	 */
	public static function getInstance(): ?self {
		return self::$instance;
	}

	public function stop(): void {
		$this->logger->quit();
		HandlerListManager::global()->unregisterAll($this->listener);
		Server::getInstance()->getCommandMap()->unregister($this->command);
		$this->tickingTask->getHandler()->cancel();

		$this->running = false;
	}

	public function getSettings(): Settings {
		return $this->settings;
	}

	public function getBanwave(): ?Banwave {
		return $this->banwave;
	}

}