<?php

namespace ethaniccc\Esoteric\thread;

use ethaniccc\Esoteric\utils\PacketUtils;
use ethaniccc\Esoteric\utils\world\NetworkChunkDeserializer;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\Thread;
use Threaded;
use Volatile;
use function count;
use function memory_get_usage;
use function microtime;
use function usleep;
use function var_dump;

/**
 * Class DecompressLevelChunkThread
 * @package ethaniccc\Esoteric\thread
 * Yes, a thread - so I don't spam a bazjillion AsyncTasks *cough*
 */
class DecompressLevelChunkThread extends Thread {

	private static $callables = [];
	private $currentID = 0;

	private $queue;
	private $results;

	private const TPS = 50;

	public function __construct() {
		$this->queue = new Threaded();
		$this->results = new Volatile();
		$this->setClassLoader();
	}

	public function queue(BatchPacket $packet, callable $callable = null): void {
		$this->queue[] = $packet;
		self::$callables[] = $callable;
	}

	public function run() {
		$this->registerClassLoader();
		PacketPool::init();
		while (!$this->isKilled) {
			$start = microtime(true);
			while (($batch = $this->queue->shift()) !== null) {
				/** @var BatchPacket $batch */
				$batch->decode();
				$chunks = [];
				$gen = PacketUtils::getAllInBatch($batch);
				foreach ($gen as $buffer) {
					$packet = PacketPool::getPacket($buffer);
					if ($packet instanceof LevelChunkPacket) {
						$packet->decode();
						$chunks[] = NetworkChunkDeserializer::chunkNetworkDeserialize($packet->getExtraPayload(), $packet->getChunkX(), $packet->getChunkZ(), $packet->getSubChunkCount());
					}
				}
				$this->results[] = $chunks;
			}
			$time = microtime(true) - $start;
			if ($time <= (1 / self::TPS)) {
				usleep(1000000 / self::TPS);
			}
		}
	}

	public function executeResults(): void {
		foreach ($this->results as $key => $chunks) {
			$callable = self::$callables[$key] ?? null;
			if ($callable !== null) {
				$callable($chunks);
			}
			unset($this->results[$key]);
			unset(self::$callables[$key]);
		}
	}

}