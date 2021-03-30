<?php

namespace ethaniccc\Esoteric;

use ethaniccc\Esoteric\data\DataManager;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\listener\PlayerListener;
use ethaniccc\Esoteric\tasks\TickingTask;
use Exception;
use pocketmine\event\HandlerList;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;

final class Esoteric{

    /** @var Esoteric|null */
    private static $instance;

    /**
     * @param PluginBase $plugin
     * @param Config|null $config
     * @param bool $start
     * @throws Exception
     */
    public static function init(PluginBase $plugin, Config $config = null, bool $start = false){
        if(self::$instance !== null)
            throw new Exception("Esoteric is already started");
        self::$instance = new self($plugin, $config);
        if($start)
            self::$instance->start();
    }

    public static function getInstance() : ?self{
        return self::$instance;
    }

    /** @var PluginBase */
    public $plugin;
    /** @var PlayerListener */
    public $listener;
    /** @var DataManager */
    public $dataManager;
    /** @var Settings */
    public $settings;
    /** @var PlayerData[] */
    public $hasAlerts = [];

    /** @var TickingTask */
    private $tickingTask;

    public function __construct(PluginBase $plugin, ?Config $config){
        $this->plugin = $plugin;
        $this->settings = new Settings($config === null ? $this->getPlugin()->getConfig()->getAll() : $config->getAll());
    }

    /**
     * @throws Exception
     */
    public function start() : void{
        if(self::$instance === null)
            throw new Exception("Esoteric has not been initialized");
        assert($this->plugin !== null);
        $this->listener = new PlayerListener();
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this->plugin);
        $this->dataManager = new DataManager();
        $this->tickingTask = new TickingTask();
        $this->plugin->getScheduler()->scheduleRepeatingTask($this->tickingTask, 1);
    }

    /**
     * @throws Exception
     */
    public function stop() : void{
        if(self::$instance === null)
            throw new Exception("Esoteric has not been initialized");
        assert($this->plugin !== null);
        HandlerList::unregisterAll($this->listener);
        $this->plugin->getScheduler()->cancelTask($this->tickingTask->getTaskId());
    }

    public function getPlugin() : ?PluginBase{
        return $this->plugin;
    }

    public function getServer() : Server{
        return Server::getInstance();
    }

    public function getSettings() : Settings{
        return $this->settings;
    }

}