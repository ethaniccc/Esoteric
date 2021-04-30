<?php

namespace ethaniccc\Esoteric\network;

use pocketmine\snooze\SleeperNotifier;
use raklib\server\RakLibServer;
use raklib\server\Session;
use raklib\server\SessionManager;
use raklib\server\UDPServerSocket;
use raklib\utils\InternetAddress;
use function count;

class CustomSessionManager extends SessionManager {

	/** @var CustomRakLibServer */
	public $rakLibServer;
	/** @var SleeperNotifier */
	public $sleeper;
	/** @var \Threaded */
	public $callables;

	public function __construct(RakLibServer $server, UDPServerSocket $socket, int $maxMtuSize, SleeperNotifier $sleeper, \Threaded $callables) {
		$this->rakLibServer = $server;
		$this->sleeper = $sleeper;
		$this->callables = $callables;
		parent::__construct($server, $socket, $maxMtuSize);
	}

	public function createSession(InternetAddress $address, int $clientId, int $mtuSize): Session {
		if(count($this->sessions) > 4096){
			foreach($this->sessions as $i => $s){
				if($s->isTemporal()){
					unset($this->sessions[$i]);
					if(count($this->sessions) <= 4096){
						break;
					}
				}
			}
		}
		$this->sessions[$address->toString()] = $session = new CustomSession($this, clone $address, $clientId, $mtuSize);
		return $session;
	}

	public function removeSession(Session $session, string $reason = "unknown"): void {
		parent::removeSession($session, $reason);
	}

	public function getSessionFromIdentifier(string $identifier): ?Session {
		return $this->sessions[$identifier] ?? null;
	}

}