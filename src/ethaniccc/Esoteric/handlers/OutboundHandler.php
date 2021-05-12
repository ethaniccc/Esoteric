<?php

namespace ethaniccc\Esoteric\handlers;

use ethaniccc\Esoteric\data\Data;
use ethaniccc\Esoteric\data\sub\world\ChunkData;
use ethaniccc\Esoteric\thread\EsotericThread;
use ethaniccc\Esoteric\utils\ChunkDeserializer;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use Threaded;
use function abs;
use function serialize;
use function unserialize;
use function var_dump;

final class OutboundHandler extends Threaded {

	public function execute(ClientboundPacket $packet, Data $data): void {
		if ($packet instanceof LevelChunkPacket) {
			if ($data->loggedIn) {
				$payload = $packet->getExtraPayload();
				$count = $packet->getSubChunkCount();
				$chunkX = $packet->getChunkX();
				$chunkZ = $packet->getChunkZ();
				EsotericThread::getInstance()->networkStackLatencyHandler->queue($data->identifier, function (int $timestamp) use ($data, $payload, $count, $chunkX, $chunkZ): void {
					$data->world->addChunk(ChunkDeserializer::deserialize($payload, $count), $chunkX, $chunkZ);
				});
			} else {
				$data->world->addChunk(ChunkDeserializer::deserialize($packet->getExtraPayload(), $packet->getSubChunkCount()), $packet->getChunkX(), $packet->getChunkZ());
			}
		} elseif ($packet instanceof NetworkChunkPublisherUpdatePacket) {
			$chunkDist = $packet->radius >> 4;
			if ($data->loggedIn) {
				EsotericThread::getInstance()->networkStackLatencyHandler->queue($data->identifier, function (int $timestamp) use ($data, $chunkDist): void {
					$currentChunk = $data->world->getChunkByHash($data->currentChunkHash);
					if ($currentChunk === null) {
						EsotericThread::getInstance()->logger->debug("No chunks in virtual world (unexpected)");
						return;
					}
					foreach ($data->world->getAllChunks() as $otherChunk) {
						/** @var ChunkData $otherChunk */
						if (abs($currentChunk->getX() - $otherChunk->getX()) >= $chunkDist || abs($currentChunk->getZ() - $otherChunk->getZ()) >= $chunkDist) {
							$data->world->removeChunk($otherChunk->getX(), $otherChunk->getZ());
						}
					}
				});
			} else {
				if ($data->currentPosition->equals(new Vector3(0, 0, 0))) {
					return;
				}
				$currentChunk = $data->world->getChunkByHash($data->currentChunkHash);
				if ($currentChunk === null) {
					EsotericThread::getInstance()->logger->debug("No chunks in virtual world (unexpected)");
					return;
				}
				foreach ($data->world->getAllChunks() as $otherChunk) {
					/** @var ChunkData $otherChunk */
					if (abs($currentChunk->getX() - $otherChunk->getX()) >= $chunkDist || abs($currentChunk->getZ() - $otherChunk->getZ()) >= $chunkDist) {
						$data->world->removeChunk($otherChunk->getX(), $otherChunk->getZ());
					}
				}
			}
		} elseif ($packet instanceof UpdateBlockPacket) {
			$vector = serialize(new Vector3($packet->x, $packet->y, $packet->z));
			$runtimeID = $packet->blockRuntimeId;
			// FFS, callables need to be serializable because NetworkStackLatencyHandler uses Threaded arrays
			EsotericThread::getInstance()->networkStackLatencyHandler->queue($data->identifier, function (int $timestamp) use ($vector, $runtimeID, $data): void {
				$data->world->setBlock(unserialize($vector), $runtimeID);
			});
		}
	}

}