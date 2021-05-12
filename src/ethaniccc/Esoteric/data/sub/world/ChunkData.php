<?php

namespace ethaniccc\Esoteric\data\sub\world;

use pocketmine\world\format\Chunk;
use Threaded;

final class ChunkData extends Threaded {

	/** @var Chunk */
	private $chunk;
	/** @var int */
	private $chunkX, $chunkZ;

	public function __construct(Chunk $chunk, int $chunkX, int $chunkZ) {
		$this->chunk = $chunk;
		$this->chunkX = $chunkX;
		$this->chunkZ = $chunkZ;
	}

	public function getChunk(): Chunk {
		return $this->chunk;
	}

	public function getX(): int {
		return $this->chunkX;
	}

	public function getZ(): int {
		return $this->chunkZ;
	}

}