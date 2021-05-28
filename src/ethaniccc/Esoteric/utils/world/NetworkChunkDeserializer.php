<?php

namespace ethaniccc\Esoteric\utils\world;

use pocketmine\block\BlockLegacyIds;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\BiomeArray;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;
use pocketmine\world\format\PalettedBlockArray;

/**
 * Class NetworkChunkDeserializer
 * @package ethaniccc\Esoteric\utils\world
 * Fun fact: we might not even this this LMAO
 */
final class NetworkChunkDeserializer {

	public static function deserialize(string $payload, int $subChunkCount) : Chunk{
		$stream = new BinaryStream($payload);

		$subChunks = [];
		for($y = 0; $y < $subChunkCount; ++$y){
			$stream->getByte(); //version
			$layers = [];
			for($l = 0, $layerCount = $stream->getByte(); $l < $layerCount; ++$l){
				$layers[] = self::deserializePalettedBlockArray($stream);
			}
			$subChunks[$y] = new SubChunk(BlockLegacyIds::AIR << 4, $layers);
		}

		$biomeIdArray = $stream->get(256);

		$stream->getByte(); //border block array count

		// TODO: tiles

		return new Chunk($subChunks, null, null, new BiomeArray($biomeIdArray));
	}

	public static function deserializePalettedBlockArray(BinaryStream $stream) : PalettedBlockArray{
		$bitsPerBlock = $stream->getByte() >> 1;

		$wordArray = $stream->get(PalettedBlockArray::getExpectedWordArraySize($bitsPerBlock));

		$palette = [];
		for($i = 0, $paletteCount = $stream->getVarInt(); $i < $paletteCount; ++$i){
			$palette[$i] = $stream->getVarInt();
		}

		return PalettedBlockArray::fromData($bitsPerBlock, $wordArray, $palette);
	}

}