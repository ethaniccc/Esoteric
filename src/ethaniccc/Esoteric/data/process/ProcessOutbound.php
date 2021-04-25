<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use pocketmine\entity\Attribute;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\NetworkChunkPublisherUpdatePacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\UpdateAttributesPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\timings\TimingsHandler;

class ProcessOutbound {

	public static $baseTimings;

	public function __construct() {
		if (self::$baseTimings === null) {
			self::$baseTimings = new TimingsHandler("Esoteric Outbound Handling");
		}
	}

	public function execute(DataPacket $packet, PlayerData $data): void {
		self::$baseTimings->startTiming();
		if ($packet instanceof MovePlayerPacket) {
			if ($packet->entityRuntimeId === $data->player->getId() && ($packet->mode === MovePlayerPacket::MODE_TELEPORT || $packet->mode === MovePlayerPacket::MODE_RESET)) {
				NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data): void {
					$data->ticksSinceTeleport = 0;
				});
			}
		} elseif ($packet instanceof UpdateBlockPacket) {
			$blockVector = new Vector3($packet->x, $packet->y, $packet->z);
			foreach ($data->inboundProcessor->placedBlocks as $key => $block) {
				if ($blockVector->equals($block) && $block->getId() === RuntimeBlockMapping::fromStaticRuntimeId($packet->blockRuntimeId)[0]) {
					unset($data->inboundProcessor->placedBlocks[$key]);
					break;
				}
			}
		} elseif ($packet instanceof SetActorMotionPacket && $packet->entityRuntimeId === $data->player->getId()) {
			NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data, $packet): void {
				$data->motion = $packet->motion;
				$data->ticksSinceMotion = 0;
			});
		} elseif ($packet instanceof MobEffectPacket) {
			if ($packet->entityRuntimeId === $data->player->getId()) {
				switch ($packet->eventId) {
					case MobEffectPacket::EVENT_ADD:
						$effectData = new EffectData();
						$effectData->effectId = $packet->effectId;
						$effectData->ticks = $packet->duration - 1;
						$effectData->amplifier = $packet->amplifier + 1;
						NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data, $effectData): void {
							$data->effects[$effectData->effectId] = $effectData;
						});
						break;
					case MobEffectPacket::EVENT_MODIFY:
						$effectData = $data->effects[$packet->effectId] ?? null;
						if ($effectData === null)
							return;
						NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($effectData, $packet): void {
							$effectData->amplifier = $packet->amplifier + 1;
							$effectData->ticks = $packet->amplifier - 1;
						});
						break;
					case MobEffectPacket::EVENT_REMOVE:
						if (isset($data->effects[$packet->effectId])) {
							// removed before the effect duration has wore off client-side
							NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data, $packet): void {
								unset($data->effects[$packet->effectId]);
							});
						}
						break;
				}
			}
		} elseif ($packet instanceof SetPlayerGameTypePacket) {
			$mode = $packet->gamemode;
			NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data, $mode): void {
				$data->gamemode = $mode;
			});
		} elseif ($packet instanceof SetActorDataPacket && $data->player->getId() === $packet->entityRuntimeId) {
			if ($data->immobile !== ($currentImmobile = $data->player->isImmobile())) {
				NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data, $currentImmobile): void {
					$data->immobile = $currentImmobile;
				});
			}
		} elseif ($packet instanceof NetworkChunkPublisherUpdatePacket) {
			if (!$data->loggedIn) {
				$data->inLoadedChunk = true;
				$data->chunkSendPosition = new Vector3($packet->x, $packet->y, $packet->z);
			} else {
				if ($data->chunkSendPosition->distance($data->currentLocation->floor()) > $data->player->getViewDistance() * 16) {
					$data->inLoadedChunk = false;
					NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($packet, $data): void {
						$data->inLoadedChunk = true;
						$data->chunkSendPosition = new Vector3($packet->x, $packet->y, $packet->z);
					});
				}
			}
		} elseif ($packet instanceof AdventureSettingsPacket) {
			NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($packet, $data): void {
				$data->isFlying = $packet->getFlag(AdventureSettingsPacket::FLYING) || $packet->getFlag(AdventureSettingsPacket::NO_CLIP);
			});
		} elseif ($packet instanceof ActorEventPacket && $packet->entityRuntimeId === $data->player->getId()) {
			switch ($packet->event) {
				case ActorEventPacket::RESPAWN:
					NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data): void {
						$data->isAlive = true;
					});
					break;
			}
		} elseif ($packet instanceof UpdateAttributesPacket && $packet->entityRuntimeId === $data->player->getId()) {
			foreach ($packet->entries as $attribute) {
				if ($attribute->getId() === Attribute::HEALTH) {
					if ($attribute->getValue() <= 0) {
						NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data): void {
							$data->isAlive = false;
						});
					} elseif ($attribute->getValue() > 0 && !$data->isAlive) {
						NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data): void {
							$data->isAlive = true;
						});
					}
				}
			}
		}
		self::$baseTimings->stopTiming();
	}

}