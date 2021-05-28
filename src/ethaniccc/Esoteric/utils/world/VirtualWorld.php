<?php

namespace ethaniccc\Esoteric\utils\world;

use Error;
use ethaniccc\Esoteric\utils\world\ChunkData;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class VirtualWorld {

	/** @var ChunkData[] */
	private $chunks = [];

	public function addChunk(Chunk $chunk, int $chunkX, int $chunkZ): void {
		if (isset($this->chunks[World::chunkHash($chunkX, $chunkZ)])) {
			unset($this->chunks[World::chunkHash($chunkX, $chunkZ)]);
		}
		$this->chunks[World::chunkHash($chunkX, $chunkZ)] = new ChunkData($chunk, $chunkX, $chunkZ);
	}

	public function getChunk(int $chunkX, int $chunkZ): ?ChunkData {
		return $this->chunks[World::chunkHash($chunkX, $chunkZ)] ?? null;
	}

	public function getChunkByHash(int $hash): ?ChunkData {
		return $this->chunks[$hash] ?? null;
	}

	public function getAllChunks(): array {
		return $this->chunks;
	}

	public function removeChunk(int $chunkX, int $chunkZ): void {
		unset($this->chunks[World::chunkHash($chunkX, $chunkZ)]);
	}

	public function getBlock(Vector3 $pos): Block {
		$pos = $pos->floor();
		return $this->getBlockAt($pos->x, $pos->y, $pos->z);
	}

	public function getBlockAt(int $x, int $y, int $z): Block {
		$chunk = $this->getChunk($x >> 4, $z >> 4);
		if ($chunk === null) {
			$block = VanillaBlocks::AIR();
			$block->position(Server::getInstance()->getWorldManager()->getDefaultWorld(), $x, $y, $z);
			return $block;
		}
		$state = $chunk->getChunk()->getFullBlock($x & 0x0f, $y, $z & 0x0f);
		$block = clone BlockFactory::getInstance()->fromFullBlock($state);
		$block->position(Server::getInstance()->getWorldManager()->getDefaultWorld(), $x, $y, $z);
		return $block;
	}

	public function setBlock(Vector3 $pos, int $id): void {
		$pos = $pos->floor();
		$chunk = $this->getChunk($pos->x >> 4, $pos->z >> 4);
		if ($chunk === null) {
			return;
		}
		$chunk->getChunk()->setFullBlock($pos->x & 0x0f, $pos->y, $pos->z & 0x0f, $id);
	}

}