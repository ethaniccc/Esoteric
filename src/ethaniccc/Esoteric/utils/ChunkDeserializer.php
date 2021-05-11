<?php

namespace ethaniccc\Esoteric\utils;

use Exception;
use pocketmine\block\BlockLegacyIds;
use pocketmine\data\bedrock\LegacyBlockIdToStringIdMap;
use pocketmine\nbt\BaseNbtSerializer;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\utils\BinaryStream;
use pocketmine\world\format\Chunk;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\world\format\io\exception\CorruptedChunkException;
use pocketmine\world\format\SubChunk;
use pocketmine\world\format\PalettedBlockArray;
use function var_dump;

final class ChunkDeserializer {

	public static function deserialize(string $data, int $subChunkCount, int $chunkX, int $chunkZ): Chunk {
		return new Chunk();
	}

	public static function deserializePaletted(BinaryStream $stream): PalettedBlockArray {
		$bitsPerBlock = $stream->getByte() >> 1;
		try{
			$words = $stream->get(PalettedBlockArray::getExpectedWordArraySize($bitsPerBlock));
		}catch(\InvalidArgumentException $e){
			throw new CorruptedChunkException("Failed to deserialize paletted storage: " . $e->getMessage(), 0, $e);
		}
		$nbt = new LittleEndianNbtSerializer();
		$palette = [];
		$idMap = LegacyBlockIdToStringIdMap::getInstance();
		for($i = 0, $paletteSize = $stream->getLInt(); $i < $paletteSize; ++$i){
			$offset = $stream->getOffset();
			$tag = $nbt->read($stream->getBuffer(), $offset)->mustGetCompoundTag();
			$stream->setOffset($offset);

			$id = $idMap->stringToLegacy($tag->getString("name")) ?? BlockLegacyIds::INFO_UPDATE;
			$data = $tag->getShort("val");
			if($id === BlockLegacyIds::AIR){
				//TODO: quick and dirty hack for artifacts left behind by broken world editors
				//we really need a proper state fixer, but this is a pressing issue.
				$data = 0;
			}
			$palette[] = ($id << 4) | $data;
		}
		return PalettedBlockArray::fromData($bitsPerBlock, $words, $palette);
	}

}