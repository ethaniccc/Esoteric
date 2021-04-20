<?php

namespace ethaniccc\Esoteric\utils;

use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\BatchPacket;

class PacketUtils {

	public static function getAllInBatch(BatchPacket $packet): array {
		$stream = new NetworkBinaryStream($packet->payload);
		$arr = [];
		while (!$stream->feof()) {
			$arr[] = $stream->getString();
		}
		return $arr;
	}

	public static function getFirst(BatchPacket $packet): Pair {
		$stream = new NetworkBinaryStream($packet->payload);
		$buff = $stream->feof() ? "" : $stream->getString();
		return new Pair($buff, $stream->feof());
	}

}