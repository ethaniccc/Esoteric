<?php

namespace ethaniccc\Esoteric\data\process;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\effect\EffectData;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\MobEffectPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;

class ProcessOutbound {

	public function execute(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof MovePlayerPacket) {
			if ($packet->entityRuntimeId === $data->player->getId() && ($packet->mode === MovePlayerPacket::MODE_TELEPORT || $packet->mode === MovePlayerPacket::MODE_RESET)) {
				NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data): void {
					$data->ticksSinceTeleport = 0;
				});
			}
		} elseif ($packet instanceof UpdateBlockPacket) {
			$blockVector = new Vector3($packet->x, $packet->y, $packet->z);
			if ($packet->blockRuntimeId !== 134 && in_array($blockVector, $data->inboundProcessor->blockPlaceVectors)) {
				foreach ($data->inboundProcessor->blockPlaceVectors as $key => $vector) {
					if ($blockVector->equals($vector)) {
						unset($data->inboundProcessor->blockPlaceVectors[$key]);
					}
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
						});
						break;
					case MobEffectPacket::EVENT_REMOVE:
						// assume the client has already removed effects client-side
						unset($data->effects[$packet->effectId]);
						break;
				}
			}
		} elseif ($packet instanceof SetPlayerGameTypePacket) {
			$mode = $packet->gamemode;
			NetworkStackLatencyHandler::send($data, NetworkStackLatencyHandler::random(), function (int $timestamp) use ($data, $mode): void {
				$data->gamemode = $mode;
				if ($mode === 3 || $mode === 4) {
					$data->isFlying = true;
				} else {
					$data->isFlying = $data->hasFlyFlag;
				}
			});
		}
	}

}