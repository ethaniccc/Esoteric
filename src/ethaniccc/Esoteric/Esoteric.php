<?php

namespace ethaniccc\Esoteric;

use ethaniccc\Esoteric\command\EsotericCommand;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\PlayerDataManager;
use ethaniccc\Esoteric\listener\EsotericEventListener;
use ethaniccc\Esoteric\protocol\PlayerAuthInputPacket;
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
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use const PTHREADS_INHERIT_NONE;

final class Esoteric{

	/** @var Esoteric|null */
	private static ?Esoteric $instance = null;

	/** @var bool */
	public bool $running = false;
	/** @var Plugin - Plugin that initialized Esoteric. */
	public Plugin $plugin;
	/** @var Settings */
	public Settings $settings;
	/** @var LoggerThread */
	public LoggerThread $logger;
	/** @var EsotericEventListener */
	public EsotericEventListener $listener;
	/** @var PlayerData[] */
	public array $hasAlerts = [];
	/** @var PlayerDataManager */
	public PlayerDataManager $dataManager;
	/** @var string[] */
	public array $logCache = [];
	/** @var TickingTask */
	public TickingTask $tickingTask;
	/** @var Banwave|null */
	public ?Banwave $banwave = null;
	/** @var EsotericCommand */
	public EsotericCommand $command;

	/**
	 * Esoteric constructor.
	 *
	 * @param PluginBase $plugin
	 * @param Config     $config
	 */
	private function __construct(PluginBase $plugin, Config $config){
		$this->plugin = $plugin;
		$this->settings = new Settings($config->getAll());
		$this->logger = new LoggerThread($this->getPlugin()->getDataFolder() . "esoteric.log");
		$this->listener = new EsotericEventListener();
		$this->dataManager = new PlayerDataManager();
		$this->tickingTask = new TickingTask();
	}

	/**
	 * @param PluginBase $plugin - Plugin to initialize Esoteric.
	 * @param Config     $settings - Configuration for Esoteric.
	 * @param bool       $start - If Esoteric should start after initialization.
	 *
	 * @throws Exception
	 */
	public static function init(PluginBase $plugin, Config $settings, bool $start = false) : void{
		if(self::$instance !== null){
			throw new Exception("Esoteric has already been initialized by " . self::$instance->plugin->getName());
		}
		self::$instance = new self($plugin, $settings);
		if($start){
			self::$instance->start();
		}
	}

	public function start() : void{
		if($this->running){
			return;
		}

		BlockFactory::getInstance()->register(new class() extends Cactus{
			public function __construct(){
				parent::__construct(new BlockIdentifier(BlockLegacyIds::CACTUS, 0), "Cactus", new BlockBreakInfo(0.4));
			}

			protected function recalculateCollisionBoxes() : array{
				static $shrinkSize = 1 / 16;
				return [AxisAlignedBB::one()->contract($shrinkSize, 0, $shrinkSize)]; // the shrink can cause issues
			}
		}, true);
		BlockFactory::getInstance()->register(new class() extends WoodenFence{
			public function __construct(){
				parent::__construct(new BlockIdentifier(BlockLegacyIds::FENCE, 7), "Oak Fence [Hack]", new BlockBreakInfo(2.0, BlockToolType::AXE, 0, 15.0));
			}
		});

		$this->logger->start(PTHREADS_INHERIT_NONE);
		$this->plugin->getServer()->getPluginManager()->registerEvents($this->listener, $this->plugin);
		$this->plugin->getScheduler()->scheduleRepeatingTask($this->tickingTask, 1);
		if($this->settings->getWaveSettings()["enabled"]){
			if(!mkdir($concurrentDirectory = $this->getPlugin()->getDataFolder() . "banwaves") && !is_dir($concurrentDirectory)){
				throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
			}
			$count = count(scandir($this->getPlugin()->getDataFolder() . "banwaves")) - 2;
			if($count === 0){
				Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask($this->getPlugin()->getDataFolder() . "banwaves/banwave-1.json", function(Banwave $banwave) : void{
					$this->banwave = $banwave;
				}));
			}else{
				$filtered = array_filter(scandir($this->getPlugin()->getDataFolder() . "banwaves"), static function(string $file) : bool{
					return strtolower(($array = explode(".", $file))[count($array) - 1]) === "json";
				});
				Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask($this->getPlugin()->getDataFolder() . "banwaves/" . $filtered[max(array_keys($filtered))], function(Banwave $banwave) : void{
					$this->banwave = $banwave;
				}));
			}
		}

		// TODO: Remove PlayerAuthInputPacket override when BedrockProtocol gets updated
		PacketPool::getInstance()->registerPacket(new PlayerAuthInputPacket());

		$this->command = new EsotericCommand();
		Server::getInstance()->getCommandMap()->register($this->plugin->getName(), $this->command);
		$this->running = true;
	}

	public function getPlugin() : PluginBase{
		return $this->plugin;
	}

	/**
	 * @return Esoteric|null
	 */
	public static function getInstance() : ?self{
		return self::$instance;
	}

	public function stop() : void{
		$this->logger->quit();
		HandlerListManager::global()->unregisterAll($this->listener);
		Server::getInstance()->getCommandMap()->unregister($this->command);
		$this->tickingTask->getHandler()?->cancel();

		$this->running = false;
	}

	public function getSettings() : Settings{
		return $this->settings;
	}

	public function getBanwave() : ?Banwave{
		return $this->banwave;
	}

}