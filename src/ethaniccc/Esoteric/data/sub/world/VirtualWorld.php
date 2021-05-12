<?php

namespace ethaniccc\Esoteric\data\sub\world;

use ethaniccc\Esoteric\thread\EsotericThread;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use Volatile;
use Threaded;

final class VirtualWorld extends Volatile {

	/** @var ChunkData[] */
	private $chunks;

	public function __construct() {
		$this->chunks = new Volatile();
	}

	public function addChunk(Chunk $chunk, int $chunkX, int $chunkZ): void {
		$this->chunks[World::chunkHash($chunkX, $chunkZ)] = new ChunkData($chunk, $chunkX, $chunkZ);
	}

	public function getChunk(int $chunkX, int $chunkZ): ?ChunkData {
		return $this->chunks[World::chunkHash($chunkX, $chunkZ)] ?? null;
	}

	public function getChunkByHash(int $hash): ?ChunkData {
		return $this->chunks[$hash] ?? null;
	}

	public function getAllChunks(): Volatile {
		return $this->chunks;
	}

	public function removeChunk(int $chunkX, int $chunkZ): void {
		unset($this->chunks[World::chunkHash($chunkX, $chunkZ)]);
	}

	public function getBlock(Vector3 $pos): Block {
		$pos = $pos->floor();
		$chunk = $this->getChunk($pos->x >> 4, $pos->z >> 4);
		if ($chunk === null) {
			return VanillaBlocks::AIR();
		}
		$state = $chunk->getChunk()->getFullBlock($pos->x & 0x0f, $pos->y, $pos->z & 0x0f);
		return BlockFactory::getInstance()->fromFullBlock(RuntimeBlockMapping::getInstance()->fromRuntimeId($state));
	}

	public function setBlock(Vector3 $pos, int $runtimeID): void {
		$pos = $pos->floor();
		$chunk = $this->getChunk($pos->x >> 4, $pos->z >> 4);
		if ($chunk === null) {
			EsotericThread::getInstance()->logger->debug("Chunk to update block in is null");
			return;
		}
		$chunk->getChunk()->setFullBlock($pos->x & 0x0f, $pos->y, $pos->z & 0x0f, $runtimeID);
	}

}