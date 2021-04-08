<?php

namespace ethaniccc\Esoteric;

use ethaniccc\Esoteric\command\EsotericCommand;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\PlayerDataManager;
use ethaniccc\Esoteric\listener\PMMPListener;
use ethaniccc\Esoteric\tasks\TickingTask;
use Exception;
use pocketmine\event\HandlerList;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;

final class Esoteric {

	/** @var Esoteric|null */
	private static $instance;
	/** @var PluginBase */
	private $plugin;
	/** @var Settings */
	private $settings;
	/** @var PlayerDataManager */
	private $dataManager;
	/** @var PlayerData[] */
	public $hasAlerts = [];
	/** @var TickingTask */
	private $tickingTask;
	/** @var EsotericCommand */
	private $command;
	/** @var PMMPListener */
	private $listener;

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
	 * @throws Exception
	 */
	public static function init(PluginBase $plugin, Config $config = null, bool $start = false): void {
		if (self::$instance !== null) {
			throw new Exception("Esoteric is already started");
		}
		self::$instance = new self($plugin, $config);
		if ($start) {
			self::$instance->start();
		}
	}

	/**
	 * @throws Exception
	 */
	public static function getInstance(): Esoteric {
		if (self::$instance === null) {
			throw new Exception("Esoteric is not started");
		}
		return self::$instance;
	}

	private function start(): void {
		$this->listener = new PMMPListener();
		Server::getInstance()->getPluginManager()->registerEvents($this->listener, $this->plugin);
		$this->plugin->getScheduler()->scheduleRepeatingTask($this->tickingTask, 1);
		$this->command = new EsotericCommand();
		Server::getInstance()->getCommandMap()->register($this->plugin->getName(), $this->command);
	}

	public function stop(): void {
		$this->plugin->getScheduler()->cancelTask($this->tickingTask->getTaskId());
		Server::getInstance()->getCommandMap()->unregister($this->command);
		HandlerList::unregisterAll($this->listener);
	}

	public function getSettings(): Settings {
		return $this->settings;
	}

	public function getDataManager(): PlayerDataManager {
		return $this->dataManager;
	}
}