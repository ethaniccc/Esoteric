<?php

namespace ethaniccc\Esoteric\network;

use pocketmine\snooze\SleeperNotifier;
use raklib\server\RakLibServer;
use raklib\server\SessionManager;
use raklib\server\UDPServerSocket;
use raklib\utils\InternetAddress;
use pocketmine\Thread;

class CustomRakLibServer extends RakLibServer {

	/** @var InternetAddress */
	private $addr;
	private $classLoader;
	private $callableSleeper;
	private $callables;

	public function __construct(\ThreadedLogger $logger, string $autoloaderPath, InternetAddress $address, \ClassLoader $loader, int $maxMtuSize = 1492, ?int $overrideProtocolVersion = null, ?SleeperNotifier $sleeper = null, SleeperNotifier $callableSleeper = null, \Threaded $callables = null) {
		$this->addr = $address;
		$this->classLoader = $loader;
		$this->callableSleeper = $callableSleeper;
		$this->callables = $callables;
		parent::__construct($logger, $autoloaderPath, $address, $maxMtuSize, $overrideProtocolVersion, $sleeper);
	}

	public function run(): void {
		require $this->loaderPath;
		$this->classLoader->register(false);
		try{

			gc_enable();
			error_reporting(-1);
			ini_set("display_errors", '1');
			ini_set("display_startup_errors", '1');

			set_error_handler([$this, "errorHandler"], E_ALL);
			register_shutdown_function([$this, "shutdownHandler"]);

			$socket = new UDPServerSocket($this->addr);
			new CustomSessionManager($this, $socket, $this->maxMtuSize, $this->callableSleeper, $this->callables);
		}catch(\Throwable $e){
			$this->logger->logException($e);
		}
	}


}