<?php

namespace ethaniccc\Esoteric;

use ethaniccc\Esoteric\network\RaklibOverride;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\tasks\TickingTask;
use ethaniccc\Esoteric\thread\EsotericThread;
use Exception;
use pocketmine\event\HandlerListManager;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use function sleep;
use function var_dump;
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
	/** @var EsotericThread */
	public $thread;
	/** @var Listener */
	public $listener;
	/** @var TickingTask */
	public $tickingTask;

	/**
	 * Esoteric constructor.
	 * @param PluginBase $plugin
	 * @param Config|null $config
	 */
	private function __construct(PluginBase $plugin, ?Config $config) {
		$this->plugin = $plugin;
		$this->settings = new Settings($config->getAll());
		$this->listener = new Listener();
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

		$this->thread = new EsotericThread($this->getPlugin()->getLogger());
		$this->thread->start(PTHREADS_INHERIT_NONE);

		$this->tickingTask = new TickingTask();
		$this->getPlugin()->getScheduler()->scheduleRepeatingTask($this->tickingTask, 1);

		Server::getInstance()->getPluginManager()->registerEvents($this->listener, $this->getPlugin());

		PacketPool::getInstance()->registerPacket(new PlayerAuthInputPacket());
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
		$this->running = false;
	}

	public function getSettings(): Settings {
		return $this->settings;
	}

}