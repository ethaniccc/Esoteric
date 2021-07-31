<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use pocketmine\entity\Attribute;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Server;
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
				$handler->send($data, static function (int $timestamp) use (&$data): void {
					$data->ticksSinceTeleport = 0;
				});
			}
		} elseif ($packet instanceof UpdateBlockPacket) {
			$blockVector = new Vector3($packet->x, $packet->y, $packet->z);
			$blk = $data->player->getLevel()->getBlock($blockVector, false, false);
			foreach ($data->inboundProcessor->placedBlocks as $key => $block) {
				// check if the block's position sent in UpdateBlockPacket is the same as the placed block
				// and if the block runtime ID sent in the packet equals the
				if ($blockVector->equals($block) && $block->getRuntimeId() === $packet->blockRuntimeId) {
					unset($data->inboundProcessor->placedBlocks[$key]);
					break;
				}
			}
			$handler->send($data, static function (int $timestamp) use (&$data, $blk, $packet): void {
				$real = RuntimeBlockMapping::fromStaticRuntimeId($packet->blockRuntimeId);
				$data->world->setBlock(new Vector3($packet->x, $packet->y, $packet->z), $real[0], ($blk->getId() === $real[0] ? $blk->getDamage() : 0)/** <- hack to get around meta being screwed up.... */);
			});
		} elseif ($packet instanceof SetActorMotionPacket && $packet->entityRuntimeId === $data->player->getId()) {
			$handler->send($data, static function (int $timestamp) use (&$data, $packet): void {
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
					$handler->send($data, static function (int $timestamp) use (&$data, $effectData): void {
						$data->effects[$effectData->effectId] = $effectData;
					});
					break;
				case MobEffectPacket::EVENT_MODIFY:
					$effectData = $data->effects[$packet->effectId] ?? null;
					if ($effectData === null) {
						return;
					}
					$handler->send($data, static function (int $timestamp) use (&$effectData, $packet): void {
						$effectData->amplifier = $packet->amplifier + 1;
						$effectData->ticks = $packet->duration;
					});
					break;
				case MobEffectPacket::EVENT_REMOVE:
					if (isset($data->effects[$packet->effectId])) {
						// removed before the effect duration has wore off client-side
						$handler->send($data, static function (int $timestamp) use (&$data, $packet): void {
							unset($data->effects[$packet->effectId]);
						});
					}
					break;
			}
		} elseif ($packet instanceof SetPlayerGameTypePacket) {
			$mode = $data->player->getGamemode(); // why is spectator sent as creative?
			$handler->send($data, static function (int $timestamp) use (&$data, $mode): void {
				$data->gamemode = $mode;
			});
		} elseif ($packet instanceof SetActorDataPacket && $data->player->getId() === $packet->entityRuntimeId) {
			if ($data->immobile !== ($currentImmobile = $data->player->isImmobile())) {
				if ($data->loggedIn) {
					$handler->send($data, static function (int $timestamp) use (&$data, $currentImmobile): void {
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
				$data->loggedIn ? $handler->send($data, static function (int $timestamp) use (&$data, $hitboxWidth): void {
					$data->hitboxWidth = $hitboxWidth;
				}) : $data->hitboxWidth = $hitboxWidth;
			}
			if ($hitboxHeight !== $data->hitboxWidth) {
				$data->loggedIn ? $handler->send($data, static function (int $timestamp) use (&$data, $hitboxHeight): void {
					$data->hitboxHeight = $hitboxHeight;
				}) : $data->hitboxHeight = $hitboxHeight;
			}
		} elseif ($packet instanceof NetworkChunkPublisherUpdatePacket) {
			$handler->send($data, static function (int $timestamp) use ($packet, &$data): void {
				$data->chunkSendPosition = new Vector3($packet->x, $packet->y, $packet->z);
				$toRemove = $data->world->getAllChunks();
				$centerX = $packet->x >> 4;
				$centerZ = $packet->z >> 4;
				$radius = $packet->radius / 16;
				for ($x = 0; $x < $radius; ++$x) {
					for ($z = 0; $z <= $x; ++$z) {
						if (($x ** 2 + $z ** 2) > $radius ** 2) {
							break;
						}
						$index = Level::chunkHash($centerX + $x, $centerZ + $z);
						if ($data->world->isValidChunk($index)) {
							unset($toRemove[$index]);
						}
						$index = Level::chunkHash($centerX - $x - 1, $centerZ + $z);
						if ($data->world->isValidChunk($index)) {
							unset($toRemove[$index]);
						}
						$index = Level::chunkHash($centerX + $x, $centerZ - $z - 1);
						if ($data->world->isValidChunk($index)) {
							unset($toRemove[$index]);
						}
						$index = Level::chunkHash($centerX - $x - 1, $centerZ - $z - 1);
						if ($data->world->isValidChunk($index)) {
							unset($toRemove[$index]);
						}
						if ($x !== $z) {
							$index = Level::chunkHash($centerX + $z, $centerZ + $x);
							if ($data->world->isValidChunk($index)) {
								unset($toRemove[$index]);
							}
							$index = Level::chunkHash($centerX - $z - 1, $centerZ + $x);
							if ($data->world->isValidChunk($index)) {
								unset($toRemove[$index]);
							}
							$index = Level::chunkHash($centerX + $z, $centerZ - $x - 1);
							if ($data->world->isValidChunk($index)) {
								unset($toRemove[$index]);
							}
							$index = Level::chunkHash($centerX - $z - 1, $centerZ - $x - 1);
							if ($data->world->isValidChunk($index)) {
								unset($toRemove[$index]);
							}
						}
					}
				}
				foreach (array_keys($toRemove) as $hash) {
					$data->world->removeChunkByHash($hash);
				}
			});
		} elseif ($packet instanceof AdventureSettingsPacket) {
			$handler->send($data, static function (int $timestamp) use ($packet, &$data): void {
				$data->isFlying = $packet->getFlag(AdventureSettingsPacket::FLYING) || $packet->getFlag(AdventureSettingsPacket::NO_CLIP);
				$data->isClipping = $packet->getFlag(AdventureSettingsPacket::NO_CLIP);
			});
		} elseif ($packet instanceof ActorEventPacket && $packet->entityRuntimeId === $data->player->getId()) {
			switch ($packet->event) {
				case ActorEventPacket::RESPAWN:
					$handler->send($data, static function (int $timestamp) use (&$data): void {
						$data->isAlive = true;
					});
					break;
			}
		} elseif ($packet instanceof UpdateAttributesPacket && $packet->entityRuntimeId === $data->player->getId()) {
			foreach ($packet->entries as $attribute) {
				if ($attribute->getId() === Attribute::HEALTH) {
					if ($attribute->getValue() <= 0) {
						$handler->send($data, static function (int $timestamp) use (&$data): void {
							$data->isAlive = false;
						});
					} elseif ($attribute->getValue() > 0 && !$data->isAlive) {
						$handler->send($data, static function (int $timestamp) use (&$data): void {
							$data->isAlive = true;
						});
					}
				}
			}
		} elseif ($packet instanceof CorrectPlayerMovePredictionPacket) {
			$handler->send($data, static function (int $timestamp) use (&$data): void {
				$data->ticksSinceTeleport = 0;
			});
		} elseif ($packet instanceof RemoveActorPacket) {
			$handler->send($data, static function (int $timestamp) use (&$data, $packet): void {
				$data->entityLocationMap->removeEntity($packet->entityUniqueId);
			});
		} elseif ($packet instanceof AddActorPacket || $packet instanceof AddPlayerPacket) {
			$handler->send($data, static function (int $timestamp) use (&$data, $packet): void {
				$entity = Server::getInstance()->findEntity($packet->entityRuntimeId);
				if ($entity !== null) {
					// if the entity is null, the stupid client is out-of-sync (lag possibly)
					$data->entityLocationMap->addEntity($entity, $packet->position);
				}
			});
		}
		self::$baseTimings->stopTiming();
	}

}