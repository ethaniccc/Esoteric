<?php

namespace ethaniccc\Esoteric;

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
use pocketmine\block\FenceGate;
use pocketmine\event\HandlerList;
use pocketmine\math\AxisAlignedBB;
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
	public $plugin;
	/** @var Settings */
	public $settings;
	/** @var PlayerDataManager */
	public $dataManager;
	/** @var PlayerData[] */
	public $hasAlerts = [];
	/** @var string[] */
	public $logCache = [];
	/** @var array */
	public $exemptList = [];
	/** @var Banwave|null */
	public $banwave;
	/** @var TickingTask */
	public $tickingTask;
	/** @var EsotericCommand */
	public $command;
	/** @var PMMPListener */
	public $listener;
	/** @var CustomNetworkInterface */
	public $networkInterface;
	/** @var LoggerThread */
	public $loggerThread;
	/** @var DecompressLevelChunkThread */
	public $chunkThread;

	/**
	 * Esoteric constructor.
	 * @param PluginBase $plugin
	 * @param Config|null $config
	 */
	private function __construct(PluginBase $plugin, ?Config $config) {
		$this->plugin = $plugin;
		$this->settings = new Settings($config === null ? $this->getPlugin()->getConfig()->getAll() : $config->getAll());
		$this->dataManager = new PlayerDataManager();
		$this->tickingTask = new TickingTask();
	}

	public function getPlugin(): PluginBase {
		return $this->plugin;
	}

	/**
	 * @param PluginBase $plugin
	 * @param Config|null $config
	 * @param bool $start
	 * @throws Exception
	 */
	public static function init(PluginBase $plugin, ?Config $config, bool $start = false) {
		if (self::$instance !== null)
			throw new Exception("Esoteric is already started");
		self::$instance = new self($plugin, $config);
		if ($start)
			self::$instance->start();
	}

	/**
	 * @throws Exception
	 */
	public function start(): void {
		if (self::$instance === null)
			throw new Exception("Esoteric has not been initialized");
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
		$this->command = new EsotericCommand();

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
		if (self::$instance === null)
			throw new Exception("Esoteric has not been initialized");
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

	public function getBanwave(): ?Banwave {
		return $this->banwave;
	}

	public function getServer(): Server {
		return Server::getInstance();
	}

	public function getSettings(): Settings {
		return $this->settings;
	}

}