<?php

namespace ethaniccc\Esoteric\thread;

use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\thread\Thread;
use Threaded;
use function microtime;
use function usleep;

class EsotericThread extends Thread {

	private const TICKS_PER_SECOND = 40;

	public $inboundQueue;
	public $outboundQueue;
	public $sendPacketQueue;

	public $networkStackLatencyHandler;
	public $dataManager;

	private static $instance;

	public static function getInstance(): self {
		return self::$instance;
	}

	public function __construct() {
		$this->setClassLoader();
	}

	protected function onRun(): void {
		self::$instance = $this;
		$this->inboundQueue = new Threaded();
		$this->outboundQueue = new Threaded();
		$this->sendPacketQueue = new Threaded();
		$this->registerClassLoader();
		PacketPool::getInstance()->registerPacket(new PlayerAuthInputPacket());
		while (!$this->isKilled) {
			$start = microtime(true);
			$this->tick();
			$delta = microtime(true) - $start;
			if ($delta <= (1 / self::TICKS_PER_SECOND)) {
				usleep(1000000 / self::TICKS_PER_SECOND);
			}
		}
	}

	private function tick(): void {

	}

}
