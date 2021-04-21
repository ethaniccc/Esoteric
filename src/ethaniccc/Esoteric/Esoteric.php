<?php

namespace ethaniccc\Esoteric;

use CortexPE\DiscordWebhookAPI\WebhookThread;
use ethaniccc\Esoteric\command\EsotericCommand;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\PlayerDataManager;
use ethaniccc\Esoteric\listener\PMMPListener;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use ethaniccc\Esoteric\tasks\TickingTask;
use ethaniccc\Esoteric\utils\banwave\Banwave;
use Exception;
use pocketmine\event\HandlerList;
use pocketmine\network\mcpe\RakLibInterface;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\Thread;
use pocketmine\utils\Config;
use raklib\server\ServerHandler;

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
	/** @var Banwave|null */
	public $banwave;
	/** @var TickingTask */
	public $tickingTask;
	/** @var EsotericCommand */
	public $command;
	/** @var PMMPListener */
	public $listener;
	/** @var ServerHandler */
	public $serverHandler;

	public function __construct(PluginBase $plugin, Config $config) {
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
	public static function init(PluginBase $plugin, Config $config, bool $start = false) {
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
		Server::getInstance()->getPluginManager()->registerEvents($this->listener, $this->plugin);
		if (!WebhookThread::valid()) {
			WebhookThread::init();
		}
		$this->plugin->getScheduler()->scheduleRepeatingTask($this->tickingTask, 1);
		$this->command = new EsotericCommand();
		Server::getInstance()->getCommandMap()->register($this->plugin->getName(), $this->command);
		if ($this->settings->getWaveSettings()["enabled"]) {
			@mkdir($this->getPlugin()->getDataFolder() . "banwaves");
			$count = count(scandir($this->getPlugin()->getDataFolder() . "banwaves")) - 2;
			if ($count === 0) {
				Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask($this->getPlugin()->getDataFolder() . "banwaves/banwave-1.json", function (Banwave $banwave): void {
					$this->banwave = $banwave;
				}));
			} else {
				$filtered = array_filter(scandir($this->getPlugin()->getDataFolder() . "banwaves"), function (string $file): bool {
					return strtolower(($array = explode(".", $file))[count($array) - 1]) === "json";
				});
				Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask($this->getPlugin()->getDataFolder() . "banwaves/" . $filtered[max(array_keys($filtered))], function (Banwave $banwave): void {
					$this->banwave = $banwave;
				}));
			}
		}
		foreach (Server::getInstance()->getNetwork()->getInterfaces() as $interface) {
			if ($interface instanceof RakLibInterface) {
				$reflection = new \ReflectionProperty($interface, "interface");
				$reflection->setAccessible(true);
				$this->serverHandler = $reflection->getValue($interface);
				break;
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
	}

	public function getServer(): Server {
		return Server::getInstance();
	}

	public function getSettings(): Settings {
		return $this->settings;
	}

	public function getBanwave(): ?Banwave{
		return $this->banwave;
	}

}