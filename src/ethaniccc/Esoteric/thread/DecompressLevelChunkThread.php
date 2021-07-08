<?php

namespace ethaniccc\Esoteric\thread;

use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\Thread;
use Threaded;
use Volatile;

/**
 * Class DecompressLevelChunkThread
 * @package ethaniccc\Esoteric\thread
 * Yes, a thread - so I don't spam a bazjillion AsyncTasks *cough*
 */
class DecompressLevelChunkThread extends Thread {

	private const TPS = 40;
	private static $callables = [];
	private $queue;
	private $results;

	public function __construct() {
		$this->queue = new Threaded();
		$this->results = new Volatile();
		$this->setClassLoader();
	}

	public function queue(BatchPacket $packet, callable $callable = null): void {
		$this->queue[] = $packet;
		self::$callables[] = $callable;
		$this->notify();
	}

	public function run(): void {
		$this->registerClassLoader();
		PacketPool::init();
		while (!$this->isKilled) {
			while (($batch = $this->queue->shift()) !== null) {
				/** @var BatchPacket $batch */
				$batch->decode();
				$chunks = [];
				$gen = PacketUtils::getAllInBatch($batch);
				foreach ($gen as $buffer) {
					$packet = PacketPool::getPacket($buffer);
					if ($packet instanceof LevelChunkPacket) {
						$packet->decode();
						$chunks[] = "{$packet->getChunkX()}:::::::::::{$packet->getChunkZ()}:::::::::::{$packet->getSubChunkCount()}:::::::::::{$packet->getExtraPayload()}";
					}
				}
				$this->results[] = $chunks;
			}
			$this->synchronized(function () : void {
				if (!$this->isKilled) {
					$this->wait();
				}
			});
		}
	}

	public function executeResults(): void {
		$keys = [];
		foreach ($this->results as $key => $chunks) {
			$callable = self::$callables[$key] ?? null;
			if ($callable !== null) {
				$callable($chunks);
			}
			$keys[] = $key;
		}
		unset($callable);
		foreach ($keys as $key) {
			unset($this->results[$key], self::$callables[$key]);
		}
	}

}