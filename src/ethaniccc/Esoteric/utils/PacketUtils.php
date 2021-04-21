<?php

namespace ethaniccc\Esoteric\utils;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\network\mcpe\CachedEncapsulatedPacket;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\BatchPacket;
use raklib\protocol\PacketReliability;
use raklib\RakLib;

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

	public static function sendPacketSilent(PlayerData $data, BatchPacket $packet): void {
		$interface = Esoteric::getInstance()->serverHandler;
		if(!isset($packet->__encapsulatedPacket)){
			$packet->__encapsulatedPacket = new CachedEncapsulatedPacket;
			$packet->__encapsulatedPacket->identifierACK = null;
			$packet->__encapsulatedPacket->buffer = $packet->buffer;
			$packet->__encapsulatedPacket->reliability = PacketReliability::RELIABLE_ORDERED;
			$packet->__encapsulatedPacket->orderChannel = 0;
		}
		$pk = $packet->__encapsulatedPacket;
		$interface->sendEncapsulated($data->networkIdentifier, $pk, 0 | RakLib::PRIORITY_IMMEDIATE);
	}

}