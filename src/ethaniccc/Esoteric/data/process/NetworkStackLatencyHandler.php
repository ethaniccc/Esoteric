<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\utils\PacketUtils;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use function mt_rand;

final class NetworkStackLatencyHandler {

	private static $list = [];
	private static $currentTimestamp = [];

	public static function random(bool $needsResponse = true): NetworkStackLatencyPacket {
		$pk = new NetworkStackLatencyPacket();
		$pk->needResponse = $needsResponse;
		$pk->timestamp = mt_rand(1, 1000000000000000) * 1000;
		return $pk;
	}

	public static function send(PlayerData $data, NetworkStackLatencyPacket $packet, callable $onResponse) {
		if ($packet->needResponse) {
			$timestamp = $packet->timestamp;
			$pk = new BatchPacket();
			$pk->addPacket($packet);
			$pk->encode();
			PacketUtils::sendPacketSilent($data, $pk, true, function (int $ackID) use ($data, $timestamp): void {
				$data->tickProcessor->waiting[$timestamp] = $data->currentTick;
			});
			if (!isset(self::$list[$data->hash])) {
				self::$list[$data->hash] = [];
			}
			self::$list[$data->hash][$timestamp] = $onResponse;
		}
	}

	public static function forceHandle(PlayerData $data, int $timestamp, callable $onResponse): void {
		if (!isset(self::$list[$data->hash])) {
			self::$list[$data->hash] = [];
		}
		self::$list[$data->hash][$timestamp] = $onResponse;
	}

	public static function forceSet(PlayerData $data, int $timestamp): void {

	}

	public static function execute(PlayerData $data, int $timestamp): void {
		$closure = self::$list[$data->hash][$timestamp] ?? null;
		if ($closure !== null) {
			$data->tickProcessor->response($timestamp);
			$closure($timestamp);
			unset(self::$list[$data->hash][$timestamp]);
		}
	}

	public static function remove(string $hash): void {
		unset(self::$list[$hash]);
	}

}