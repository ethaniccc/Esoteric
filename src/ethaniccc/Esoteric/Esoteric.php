<?php

namespace ethaniccc\Esoteric;

use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\PacketHooker;
use ethaniccc\Esoteric\blocks\FenceGateOverride;
use ethaniccc\Esoteric\command\EsotericCommand;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\PlayerDataManager;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\listener\PMMPListener;
use ethaniccc\Esoteric\network\CustomNetworkInterface;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use ethaniccc\Esoteric\tasks\TickingTask;
use ethaniccc\Esoteric\thread\DecompressLevelChunkThread;
use ethaniccc\Esoteric\thread\LoggerThread;
use ethaniccc\Esoteric\utils\banwave\Banwave;
use ethaniccc\Esoteric\webhook\WebhookThread;
use Exception;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\event\HandlerList;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\RakLibInterface;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use function array_filter;
use function array_keys;
use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function max;
use function mkdir;
use function scandir;
use function strtolower;
use const PHP_EOL;
use const PTHREADS_INHERIT_NONE;

final class Esoteric {

	/** @var Esoteric|null */
	private static $instance;
	/** @var PluginBase */
	public PluginBase $plugin;
	/** @var Settings */
	public Settings $settings;
	/** @var PlayerDataManager */
	public PlayerDataManager $dataManager;
	/** @var PlayerData[] */
	public array $hasAlerts = [];
	/** @var string[] */
	public array $logCache = [];
	/** @var array */
	public array $exemptList = [];
	/** @var Banwave|null */
	public ?Banwave $banwave = null;
	/** @var TickingTask */
	public TickingTask $tickingTask;
	/** @var EsotericCommand */
	public EsotericCommand $command;
	/** @var PMMPListener */
	public PMMPListener $listener;
	/** @var CustomNetworkInterface */
	public CustomNetworkInterface $networkInterface;
	/** @var LoggerThread */
	public LoggerThread $loggerThread;
	/** @var DecompressLevelChunkThread */
	public DecompressLevelChunkThread $chunkThread;
	/** @var bool */
	public bool $hasComposerDeps;
	/** @var string */
	public $autoloadPath;

	/**
	 * Esoteric constructor.
	 */
	private function __construct(PluginBase $plugin, ?Config $config, string $autoloadPath = null) {
		$this->plugin = $plugin;
		$this->settings = new Settings($config === null ? $this->getPlugin()->getConfig()->getAll() : $config->getAll());
		$this->dataManager = new PlayerDataManager();
		$this->tickingTask = new TickingTask();
		$this->autoloadPath = $autoloadPath;
		if (!file_exists($this->autoloadPath)) {
			$plugin->getLogger()->warning("Autoload file does not exist - [ignore if none of the dependencies are needed]");
			$this->autoloadPath = null;
		}
		$this->hasComposerDeps = $this->autoloadPath !== null;
	}

	public function getPlugin(): PluginBase {
		return $this->plugin;
	}

	/**
	 * @throws Exception
	 */
	public static function init(PluginBase $plugin, ?Config $config, string $autoloadPath = null, bool $start = false) {
        if (!class_exists(BaseCommand::class)) {
            throw new Exception("Commando is required for Esoteric to run");
        }

        if (self::$instance !== null) {
			throw new Exception("Esoteric is already started");
		}
		self::$instance = new self($plugin, $config, $autoloadPath);
		if ($start) {
			self::$instance->start();
		}
	}

	/**
	 * @throws Exception
	 */
	public function start(): void {
		if (self::$instance === null) {
			throw new Exception("Esoteric has not been initialized");
		}
		if ($this->hasComposerDeps) {
			require_once $this->autoloadPath;
		}
		// TODO: Get Blackfire profiling running on a PocketMine-MP server.
		/* if ($this->settings->isDebugging() && !function_exists('ray')) {
			throw new Exception("Debugging enabled, but spatie/ray was not found.");
		} */
		$this->listener = new PMMPListener();
		foreach (Server::getInstance()->getNetwork()->getInterfaces() as $interface) {
			if ($interface instanceof RakLibInterface) {
				Server::getInstance()->getNetwork()->unregisterInterface($interface);
				$interface->shutdown();
				$this->networkInterface = new CustomNetworkInterface(Server::getInstance());
				Server::getInstance()->getNetwork()->registerInterface($this->networkInterface);
				break;
			}
		}
		$this->command = new EsotericCommand($this->plugin, "ac", "The Esoteric anti-cheat command");
		$this->loggerThread = new LoggerThread($this->getPlugin()->getDataFolder() . "esoteric.log");
		$this->loggerThread->start();
		$this->chunkThread = new DecompressLevelChunkThread();
		$this->chunkThread->start(PTHREADS_INHERIT_NONE);
		Server::getInstance()->getPluginManager()->registerEvents($this->listener, $this->plugin);
		if (!WebhookThread::valid()) {
			WebhookThread::init();
		}
		PacketPool::registerPacket(new PlayerAuthInputPacket());
		$this->plugin->getScheduler()->scheduleRepeatingTask($this->tickingTask, 1);

		if (!file_exists($this->getPlugin()->getDataFolder() . "exempt.txt")) {
			file_put_contents($this->getPlugin()->getDataFolder() . "exempt.txt", "");
		}
		$contents = file_get_contents($this->getPlugin()->getDataFolder() . "exempt.txt");
		$this->exemptList = explode(PHP_EOL, $contents);
		foreach ($this->exemptList as $k => $exempt) {
			if ($exempt === "") {
				unset($this->exemptList[$k]);
			}
		}

		/**
		 * Start correcting code for some block bounding boxes. Some bounding boxes aren't 1:1 as possible
		 * with the client (e.g Fence Gates).
		 */

		BlockFactory::registerBlock(new FenceGateOverride(Block::OAK_FENCE_GATE, 0, "Oak Fence Gate"), true);
		BlockFactory::registerBlock(new FenceGateOverride(Block::SPRUCE_FENCE_GATE, 0, "Spruce Fence Gate"), true);
		BlockFactory::registerBlock(new FenceGateOverride(Block::BIRCH_FENCE_GATE, 0, "Birch Fence Gate"), true);
		BlockFactory::registerBlock(new FenceGateOverride(Block::JUNGLE_FENCE_GATE, 0, "Jungle Fence Gate"), true);
		BlockFactory::registerBlock(new FenceGateOverride(Block::DARK_OAK_FENCE_GATE, 0, "Dark Oak Fence Gate"), true);
		BlockFactory::registerBlock(new FenceGateOverride(Block::ACACIA_FENCE_GATE, 0, "Acacia Fence Gate"), true);

		/**
		 * End the ctrl+c ctrl+v madness
		 */
        if(!PacketHooker::isRegistered()) {
            PacketHooker::register($this->plugin);
        }

        Server::getInstance()->getCommandMap()->register($this->plugin->getName(), $this->command);

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
	}

	public static function getInstance(): ?self {
		return self::$instance;
	}

	/**
	 * @throws Exception
	 */
	public function stop(): void {
		if (self::$instance === null) {
			throw new Exception("Esoteric has not been initialized");
		}
		$this->plugin->getScheduler()->cancelTask($this->tickingTask->getTaskId());
		Server::getInstance()->getCommandMap()->unregister($this->command);
		HandlerList::unregisterAll($this->listener);
		if ($this->getBanwave() !== null) {
			$this->getBanwave()->update();
		}
		file_put_contents($this->plugin->getDataFolder() . "exempt.txt", implode(PHP_EOL, $this->exemptList));
		if (!Server::getInstance()->isRunning() && WebhookThread::valid()) {
			WebhookThread::getInstance()->stop();
		}
	}

	/**
	 * @return Banwave|null
	 */
	public function getBanwave(): ?Banwave {
		return $this->banwave;
	}

	/**
	 * @return Server
	 */
	public function getServer(): Server {
		return Server::getInstance();
	}

	/**
	 * @return Settings
	 */
	public function getSettings(): Settings {
		return $this->settings;
	}

}