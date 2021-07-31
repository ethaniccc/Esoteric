<?php

namespace ethaniccc\Esoteric\utils\world;

use ethaniccc\Esoteric\Esoteric;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\Vector3;

final class VirtualWorld {

	/** @var Chunk[] */
	private $chunks = [];

	public function addChunk(Chunk $chunk): void {
		$chunkX = $chunk->getX();
		$chunkZ = $chunk->getZ();
		if (isset($this->chunks[Level::chunkHash($chunkX, $chunkZ)])) {
			$this->removeChunk($chunkX, $chunkZ);
		}
		$this->chunks[Level::chunkHash($chunkX, $chunkZ)] = $chunk;
	}

	public function removeChunk(int $chunkX, int $chunkZ): void {
		$this->chunks[Level::chunkHash($chunkX, $chunkZ)] = null;
		unset($this->chunks[Level::chunkHash($chunkX, $chunkZ)]);
	}

	public function getChunkByHash(int $hash): ?Chunk {
		return $this->chunks[$hash] ?? null;
	}

	/**
	 * @return Chunk[]
	 */
	public function getAllChunks(): array {
		return $this->chunks;
	}

	public function removeChunkByHash(int $hash): void {
		unset($this->chunks[$hash]);
	}

	public function getBlock(Vector3 $pos): Block {
		$pos = $pos->floor();
		return $this->getBlockAt($pos->x, $pos->y, $pos->z);
	}

	public function getBlockAt(int $x, int $y, int $z): Block {
		$chunkHash = Level::chunkHash($x >> 4, $z >> 4);
		$chunk = $this->chunks[$chunkHash] ?? null;
		if ($chunk === null) {
			$air = new Air();
			$air->x = $x;
			$air->y = $y;
			$air->z = $z;
			return $air;
		}
		$fullState = $chunk->getFullBlock($x & 0x0f, $y, $z & 0x0f);
		$block = clone BlockFactory::getBlockStatesArray()[$fullState];
		/** @var Block $block */
		$block->x = $x;
		$block->y = $y;
		$block->z = $z;
		return $block;
	}

	public function setBlock(Vector3 $pos, int $id, int $meta): void {
		$pos = $pos->floor();
		$chunk = $this->getChunk($pos->x >> 4, $pos->z >> 4);
		if ($chunk === null) {
			Esoteric::getInstance()->getPlugin()->getLogger()->debug("Unexpected null chunk when setting block");
			return;
		}
		$chunk->setBlock($pos->x & 0x0f, $pos->y, $pos->z & 0x0f, $id, $meta);
	}

	public function getChunk(int $chunkX, int $chunkZ): ?Chunk {
		return $this->chunks[Level::chunkHash($chunkX, $chunkZ)] ?? null;
	}

	public function isValidChunk(int $x, int $z = null): bool {
		return $z === null ? isset($this->chunks[$x]) : isset($this->chunks[Level::chunkHash($x, $z)]);
	}

}