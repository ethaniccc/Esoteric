<?php

namespace ethaniccc\Esoteric\utils\world;

use pocketmine\world\format\Chunk;

final class ChunkData{

	private $chunkX, $chunkZ;
	private $chunk;

	public function __construct(Chunk $chunk, int $x, int $z){
		$this->chunk = $chunk;
		$this->chunkX = $x;
		$this->chunkZ = $z;
	}

	public function getChunk() : Chunk{
		return $this->chunk;
	}

	public function getX() : int{
		return $this->chunkX;
	}

	public function getZ() : int{
		return $this->chunkZ;
	}

}