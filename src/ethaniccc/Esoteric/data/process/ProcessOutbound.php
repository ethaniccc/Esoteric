<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use pocketmine\entity\Attribute;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\timings\TimingsHandler;
use function abs;

class ProcessOutbound {

	public static $baseTimings;

	public function __construct() {
		if (self::$baseTimings === null) {
			self::$baseTimings = new TimingsHandler("Esoteric Outbound Handling");
		}
	}

	public function execute(DataPacket $packet, PlayerData $data): void {
		self::$baseTimings->startTiming();
		$handler = NetworkStackLatencyHandler::getInstance();
		if ($packet instanceof MovePlayerPacket) {
			if ($packet->entityRuntimeId === $data->player->getId() && ($packet->mode === MovePlayerPacket::MODE_TELEPORT || $packet->mode === MovePlayerPacket::MODE_RESET)) {
				$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data): void {
					$data->ticksSinceTeleport = 0;
				});
			}
		} elseif ($packet instanceof UpdateBlockPacket) {
			$blockVector = new Vector3($packet->x, $packet->y, $packet->z);
			foreach ($data->inboundProcessor->placedBlocks as $key => $block) {
				// check if the block's position sent in UpdateBlockPacket is the same as the placed block
				// and if the block runtime ID sent in the packet equals the
				if ($blockVector->equals($block) && $block->getRuntimeId() === $packet->blockRuntimeId) {
					unset($data->inboundProcessor->placedBlocks[$key]);
					break;
				}
			}
			$handler->send($data, $handler->next($data), function (int $timestamp) use ($data, $packet): void {
				$real = RuntimeBlockMapping::fromStaticRuntimeId($packet->blockRuntimeId);
				$data->world->setBlock(new Vector3($packet->x, $packet->y, $packet->z), $real[0], 0); // ignore meta - wtf is going on??
			});
		} elseif ($packet instanceof SetActorMotionPacket && $packet->entityRuntimeId === $data->player->getId()) {
			$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data, $packet): void {
				$data->motion = $packet->motion;
				$data->ticksSinceMotion = 0;
			});
		} elseif ($packet instanceof MobEffectPacket && $packet->entityRuntimeId === $data->player->getId()) {
			switch ($packet->eventId) {
				case MobEffectPacket::EVENT_ADD:
					$effectData = new EffectData();
					$effectData->effectId = $packet->effectId;
					$effectData->ticks = $packet->duration;
					$effectData->amplifier = $packet->amplifier + 1;
					$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data, $effectData): void {
						$data->effects[$effectData->effectId] = $effectData;
					});
					break;
				case MobEffectPacket::EVENT_MODIFY:
					$effectData = $data->effects[$packet->effectId] ?? null;
					if ($effectData === null)
						return;
					$handler->send($data, $handler->next($data), static function (int $timestamp) use (&$effectData, $packet): void {
						$effectData->amplifier = $packet->amplifier + 1;
						$effectData->ticks = $packet->duration;
					});
					break;
				case MobEffectPacket::EVENT_REMOVE:
					if (isset($data->effects[$packet->effectId])) {
						// removed before the effect duration has wore off client-side
						$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data, $packet): void {
							unset($data->effects[$packet->effectId]);
						});
					}
					break;
			}
		} elseif ($packet instanceof SetPlayerGameTypePacket) {
			$mode = $packet->gamemode;
			$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data, $mode): void {
				$data->gamemode = $mode;
			});
		} elseif ($packet instanceof SetActorDataPacket && $data->player->getId() === $packet->entityRuntimeId) {
			if ($data->immobile !== ($currentImmobile = $data->player->isImmobile())) {
				if ($data->loggedIn) {
					$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data, $currentImmobile): void {
						$data->immobile = $currentImmobile;
					});
				} else {
					$data->immobile = $currentImmobile;
				}
			}
			$AABB = $data->player->getBoundingBox();
			$hitboxWidth = ($AABB->maxX - $AABB->minX) * 0.5;
			$hitboxHeight = $AABB->maxY - $AABB->minY;
			if ($hitboxWidth !== $data->hitboxWidth) {
				$data->loggedIn ? $handler->send($data, $handler->next($data), static function (int $timestamp) use ($data, $hitboxWidth): void {
					$data->hitboxWidth = $hitboxWidth;
				}) : $data->hitboxWidth = $hitboxWidth;
			}
			if ($hitboxHeight !== $data->hitboxWidth) {
				$data->loggedIn ? $handler->send($data, $handler->next($data), static function (int $timestamp) use ($data, $hitboxHeight): void {
					$data->hitboxHeight = $hitboxHeight;
				}) : $data->hitboxHeight = $hitboxHeight;
			}
		} elseif ($packet instanceof NetworkChunkPublisherUpdatePacket) {
			$handler->send($data, $handler->next($data), function (int $timestamp) use ($packet, $data): void {
				$data->chunkSendPosition = new Vector3($packet->x, $packet->y, $packet->z);
				$radius = $packet->radius >> 4;
				$chunkX = $data->chunkSendPosition->x >> 4;
				$chunkZ = $data->chunkSendPosition->z >> 4;
				foreach ($data->world->getAllChunks() as $chunk) {
					if (abs($chunk->getX() - $chunkX) > $radius || abs($chunk->getZ() - $chunkZ) > $radius) {
						$data->world->removeChunk($chunk->getX(), $chunk->getZ());
					}
				}
			});
		} elseif ($packet instanceof AdventureSettingsPacket) {
			$handler->send($data, $handler->next($data), static function (int $timestamp) use ($packet, $data): void {
				$data->isFlying = $packet->getFlag(AdventureSettingsPacket::FLYING) || $packet->getFlag(AdventureSettingsPacket::NO_CLIP);
			});
		} elseif ($packet instanceof ActorEventPacket && $packet->entityRuntimeId === $data->player->getId()) {
			switch ($packet->event) {
				case ActorEventPacket::RESPAWN:
					$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data): void {
						$data->isAlive = true;
					});
					break;
			}
		} elseif ($packet instanceof UpdateAttributesPacket && $packet->entityRuntimeId === $data->player->getId()) {
			foreach ($packet->entries as $attribute) {
				if ($attribute->getId() === Attribute::HEALTH) {
					if ($attribute->getValue() <= 0) {
						$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data): void {
							$data->isAlive = false;
						});
					} elseif ($attribute->getValue() > 0 && !$data->isAlive) {
						$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data): void {
							$data->isAlive = true;
						});
					}
				}
			}
		} elseif ($packet instanceof CorrectPlayerMovePredictionPacket) {
			$handler->send($data, $handler->next($data), static function (int $timestamp) use ($data): void {
				$data->ticksSinceTeleport = 0;
			});
		} elseif ($packet instanceof NetworkStackLatencyPacket) {
			$handler->forceSet($data, $packet->timestamp - fmod($packet->timestamp, 1000));
		}
		self::$baseTimings->stopTiming();
	}

}