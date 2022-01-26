<?php

namespace ethaniccc\Esoteric\utils\world;

use pocketmine\level\format\Chunk;
use pocketmine\level\format\SubChunk;

final class NetworkChunkDeserializer {

	public static function chunkNetworkDeserialize(string $data, int $chunkX, int $chunkZ, int $subChunkCount): ?Chunk {
		$nextPos = 0;
		$subChunks = [];
		$total = "";
		for ($y = 0; $y < 4; ++$y) {
			// ignore this information - it's irrelevant
			$nextPos += 2;
		}
		for ($i = 0; $i < $subChunkCount; ++$i) {
			$subInformation = substr($data, $nextPos + 1, 2048 + 4096);
			$subIDS = substr($subInformation, 0, 4096);
			$subData = substr($subInformation, 4096, 2048);
			$subChunks[] = new SubChunk($subIDS, $subData);
			$total .= "\x00" . $subInformation;
			// strlen(chr(0)) + strlen(IDS) + strlen(DATA)
			$nextPos += 1 + 2048 + 4096;
		}
		return new Chunk($chunkX, $chunkZ, $subChunks, [], [], "", []);
	}

}