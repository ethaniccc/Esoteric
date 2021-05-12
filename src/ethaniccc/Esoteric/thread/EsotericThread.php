<?php

namespace ethaniccc\Esoteric\thread;

use AttachableLogger;
use ethaniccc\Esoteric\data\DataManager;
use ethaniccc\Esoteric\handlers\NetworkStackLatencyHandler;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\thread\Thread;
use Threaded;
use function gc_disable;
use function microtime;
use function mt_rand;
use function usleep;
use function var_dump;

class EsotericThread extends Thread {

	private const TICKS_PER_SECOND = 50;

	/** @var Threaded */
	public $inboundQueue;
	/** @var Threaded */
	public $outboundQueue;
	/** @var Threaded */
	public $sendPacketQueue;

	/** @var NetworkStackLatencyHandler */
	public $networkStackLatencyHandler;
	/** @var Threaded */
	public $currentTimestamps;
	/** @var DataManager */
	public $dataManager;

	/** @var AttachableLogger */
	public $logger;

	/** @var bool */
	private $shouldUpdateTimestamps = false;
	/** @var string */
	private $otherDependenciesPath;

	private static $instance;

	public static function getInstance(): self {
		return self::$instance;
	}

	public function __construct(AttachableLogger $logger, ?string $otherDependenciesPath = null) {
		$this->setClassLoader();
		$this->logger = $logger;
		$this->otherDependenciesPath = $otherDependenciesPath;
	}

	public function queueInbound(string $identifier, ServerboundPacket $packet): void {
		if (!isset($this->inboundQueue[$identifier])) {
			$this->inboundQueue[$identifier] = new Threaded();
		}
		$this->inboundQueue[$identifier][] = $packet;
	}

	public function queueOutbound(array $targets, ClientboundPacket $packet): void {
		foreach ($targets as $target) {
			if (!isset($this->outboundQueue[$target])) {
				$this->outboundQueue[$target] = new Threaded();
			}
			$this->outboundQueue[$target][] = $packet;
		}
	}

	public function getCurrentTimestamp(string $identifier): int {
		return $this->currentTimestamps[$identifier] ?? -1;
	}

	public function updateTimestamps(): void {
		$this->shouldUpdateTimestamps = true;
	}

	public function queuePacket(ClientboundPacket $packet, string $identifier): void {
		if (!isset($this->sendPacketQueue[$identifier])) {
			$this->sendPacketQueue[$identifier] = new Threaded();
		}
		$this->sendPacketQueue[$identifier][] = $packet;
	}

	protected function onRun(): void {
		self::$instance = $this;
		$this->inboundQueue = new Threaded();
		$this->outboundQueue = new Threaded();
		$this->sendPacketQueue = new Threaded();
		$this->currentTimestamps = new Threaded();
		$this->dataManager = new DataManager();
		$this->networkStackLatencyHandler = new NetworkStackLatencyHandler();
		$this->registerClassLoader();
		if ($this->otherDependenciesPath !== null) {
			require $this->otherDependenciesPath;
		}
		PacketPool::getInstance()->registerPacket(new PlayerAuthInputPacket());
		while (!$this->isKilled) {
			$start = microtime(true);
			$this->tick();
			$delta = microtime(true) - $start;

			if ($delta > (1 / self::TICKS_PER_SECOND)) {
				$this->logger->debug("delta=$delta, thread catching up (no sleep)");
			} else {
				usleep(1000000 / self::TICKS_PER_SECOND);
			}
		}
	}

	private function tick(): void {
		if ($this->shouldUpdateTimestamps) {
			foreach ($this->dataManager->getAll() as $data) {
				$this->networkStackLatencyHandler->updateTimestamp($data->identifier);
				$this->currentTimestamps[$data->identifier] = $this->networkStackLatencyHandler->getCurrentTimestamp($data->identifier);
			}
			$this->shouldUpdateTimestamps = false;
		}
		foreach ($this->inboundQueue as $identifier => $queue) {
			/** @var Threaded $queue */
			while(($packet = $queue->shift()) !== null){
				/** @var ServerboundPacket $packet */
				$data = $this->dataManager->get($identifier) ?? $this->dataManager->add($identifier);
				$data->inboundHandler->execute($packet, $data);
			}
		}
		foreach ($this->outboundQueue as $identifier => $queue) {
			/** @var Threaded $queue */
			while(($packet = $queue->shift()) !== null){
				/** @var ClientboundPacket $packet */
				$data = $this->dataManager->get($identifier);
				if ($data === null) {
					return;
				}
				$data->outboundHandler->execute($packet, $data);
			}
		}
	}

	public function __sleep() {
		return [
			"sendPacketQueue",
			"inboundQueue",
			"outboundQueue",
			"currentTimestamps",
			"shouldUpdateTimestamps",
		];
	}

}
