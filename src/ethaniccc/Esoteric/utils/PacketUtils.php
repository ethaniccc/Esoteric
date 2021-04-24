<?php

namespace ethaniccc\Esoteric\utils;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\process\ACKHandler;
use ethaniccc\Esoteric\Esoteric;
use pocketmine\network\mcpe\CachedEncapsulatedPacket;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\BatchPacket;
use raklib\protocol\EncapsulatedPacket;
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

	/**
	 * @param PlayerData $data
	 * @param BatchPacket $packet
	 * @param bool $needACK
	 * @param callable|null $ackResponse
	 * This function exists so that I don't have to deal with un-needed decoding of packets that Esoteric sends. This sends a batch packet to the player
	 * without calling the DataPacketReceiveEvent.
	 */
	public static function sendPacketSilent(PlayerData $data, BatchPacket $packet, bool $needACK = false, callable $ackResponse = null): void {
		$interface = Esoteric::getInstance()->serverHandler;
		if ($needACK) {
			$pk = new EncapsulatedPacket();
			$id = ACKHandler::next($data->networkIdentifier);
			$pk->identifierACK = $id;
			$pk->buffer = $packet->buffer;
			$pk->reliability = PacketReliability::RELIABLE_ORDERED;
			$pk->orderChannel = 0;
			if ($ackResponse !== null) {
				ACKHandler::add($data->networkIdentifier, $id, $ackResponse);
			}
		} else {
			if(!isset($packet->__encapsulatedPacket)){
				$packet->__encapsulatedPacket = new CachedEncapsulatedPacket;
				$packet->__encapsulatedPacket->identifierACK = null;
				$packet->__encapsulatedPacket->buffer = $packet->buffer;
				$packet->__encapsulatedPacket->reliability = PacketReliability::RELIABLE_ORDERED;
				$packet->__encapsulatedPacket->orderChannel = 0;
			}
			$pk = $packet->__encapsulatedPacket;
		}
		$interface->sendEncapsulated($data->networkIdentifier, $pk, ($needACK ? RakLib::FLAG_NEED_ACK : 0) | RakLib::PRIORITY_IMMEDIATE);
	}

}