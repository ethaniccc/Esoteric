<?php

namespace ethaniccc\Esoteric;

use ethaniccc\Esoteric\data\DataManager;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\listener\PlayerListener;
use Exception;
use pocketmine\event\HandlerList;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

final class Esoteric{

    /** @var Esoteric|null */
    private static $instance;

    /**
     * @param PluginBase $plugin
     * @param bool $start
     * @throws Exception
     */
    public static function init(PluginBase $plugin, bool $start = false){
        if(self::$instance !== null)
            throw new Exception("Esoteric is already started");
        self::$instance = new self($plugin);
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

    public function __construct(PluginBase $plugin){
        $this->plugin = $plugin;
        $this->settings = new Settings($plugin->getConfig()->getAll());
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
        $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(int $currentTick) : void{
            $this->hasAlerts = array_filter($this->dataManager->getAll(), function(PlayerData $data) : bool{
                return $data->player->isOnline() && $data->player->hasPermission("ac.alerts") && $data->hasAlerts;
            });
        }), 40);
    }

    /**
     * @throws Exception
     */
    public function stop() : void{
        if(self::$instance === null)
            throw new Exception("Esoteric has not been initialized");
        assert($this->plugin !== null);
        HandlerList::unregisterAll($this->listener);
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