<?php

namespace ethaniccc\Esoteric;

use BadMethodCallException;
use ethaniccc\Esoteric\protocol\InputConstants;
use ethaniccc\Esoteric\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\protocol\v428\PlayerBlockAction;
use pocketmine\entity\Location;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\PlayerMovementSettings;
use pocketmine\network\mcpe\protocol\types\PlayerMovementType;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

class Listener implements \pocketmine\event\Listener {

	/** @var Location[] */
	private $lastPosition = [];

	public function onReceive(DataPacketReceiveEvent $event): void {
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();
		$identifier = $event->getOrigin()->getIp() . " "  . $event->getOrigin()->getPort();
		Esoteric::getInstance()->thread->queueInbound($identifier, $packet);
		if ($packet instanceof PlayerAuthInputPacket) {
			$event->cancel();
			if (!isset($this->lastPosition[$identifier])) {
				$this->lastPosition[$identifier] = Location::fromObject($packet->getPosition(), null, $packet->getYaw(), $packet->getPitch());
				return;
			}
			if ($player === null)
				return;
			$current = Location::fromObject($packet->getPosition(), null, $packet->getYaw(), $packet->getPitch());
			$lastPos = $this->lastPosition[$identifier];
			if (!$lastPos->equals($current)) {
				$pk = new MovePlayerPacket();
				$pk->entityRuntimeId = $player->getId();
				$pk->position = $packet->getPosition();
				$pk->yaw = $current->yaw;
				$pk->headYaw = $packet->getHeadYaw();
				$pk->pitch = $current->pitch;
				$pk->mode = MovePlayerPacket::MODE_NORMAL;
				$pk->onGround = false;
				$pk->tick = $packet->getTick();
				$player->getNetworkSession()->getHandler()->handleMovePlayer($pk);
				$this->lastPosition[$identifier] = $current;
			}

			if (InputConstants::hasFlag($packet, InputConstants::START_SPRINTING)) {
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $player->getId();
				$pk->action = PlayerActionPacket::ACTION_START_SPRINT;
				$pk->x = $current->x;
				$pk->y = $current->y;
				$pk->z = $current->z;
				$pk->face = $player->getHorizontalFacing();
				$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::STOP_SPRINTING)) {
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $player->getId();
				$pk->action = PlayerActionPacket::ACTION_STOP_SPRINT;
				$pk->x = $current->x;
				$pk->y = $current->y;
				$pk->z = $current->z;
				$pk->face = $player->getHorizontalFacing();
				$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::START_SNEAKING)) {
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $player->getId();
				$pk->action = PlayerActionPacket::ACTION_START_SNEAK;
				$pk->x = $current->x;
				$pk->y = $current->y;
				$pk->z = $current->z;
				$pk->face = $player->getHorizontalFacing();
				$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::STOP_SNEAKING)) {
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $player->getId();
				$pk->action = PlayerActionPacket::ACTION_STOP_SNEAK;
				$pk->x = $current->x;
				$pk->y = $current->y;
				$pk->z = $current->z;
				$pk->face = $player->getHorizontalFacing();
				$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}
			if (InputConstants::hasFlag($packet, InputConstants::START_JUMPING)) {
				$pk = new PlayerActionPacket();
				$pk->entityRuntimeId = $player->getId();
				$pk->action = PlayerActionPacket::ACTION_JUMP;
				$pk->x = $current->x;
				$pk->y = $current->y;
				$pk->z = $current->z;
				$pk->face = $player->getHorizontalFacing();
				$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
			}

			if ($packet->blockActions !== null) {
				foreach ($packet->blockActions as $action) {
					switch ($action->actionType) {
						case PlayerBlockAction::START_BREAK:
							$pk = new PlayerActionPacket();
							$pk->entityRuntimeId = $player->getId();
							$pk->action = PlayerActionPacket::ACTION_START_BREAK;
							$pk->x = $action->blockPos->x;
							$pk->y = $action->blockPos->y;
							$pk->z = $action->blockPos->z;
							$pk->face = $player->getHorizontalFacing();
							$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
							break;
						case PlayerBlockAction::CONTINUE:
						case PlayerBlockAction::CRACK_BREAK:
							$pk = new PlayerActionPacket();
							$pk->entityRuntimeId = $player->getId();
							$pk->action = PlayerActionPacket::ACTION_CRACK_BREAK;
							$pk->x = $action->blockPos->x;
							$pk->y = $action->blockPos->y;
							$pk->z = $action->blockPos->z;
							$pk->face = $player->getHorizontalFacing();
							$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
							break;
						case PlayerBlockAction::ABORT_BREAK:
							$pk = new PlayerActionPacket();
							$pk->entityRuntimeId = $player->getId();
							$pk->action = PlayerActionPacket::ACTION_ABORT_BREAK;
							$pk->x = $action->blockPos->x;
							$pk->y = $action->blockPos->y;
							$pk->z = $action->blockPos->z;
							$pk->face = $player->getHorizontalFacing();
							$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
							break;
						case PlayerBlockAction::STOP_BREAK:
							$pk = new PlayerActionPacket();
							$pk->entityRuntimeId = $player->getId();
							$pk->action = PlayerActionPacket::ACTION_STOP_BREAK;
							$pk->x = $current->x;
							$pk->y = $current->y;
							$pk->z = $current->z;
							$pk->face = $player->getHorizontalFacing();
							$player->getNetworkSession()->getHandler()->handlePlayerAction($pk);
							break;
						case PlayerBlockAction::PREDICT_DESTROY:
							break;
					}
				}
			}

			if ($packet->itemInteractionData !== null) {
				// maybe if :microjang: didn't make the block breaking server-side option redundant, I wouldn't be doing this... you know who to blame !
				// hahaha... skidding PMMP go brrrt
				$player->doCloseInventory();
				$item = $player->getInventory()->getItemInHand();
				$oldItem = clone $item;
				$currentBlock = $player->getWorld()->getBlock($packet->itemInteractionData->blockPos);
				$canInteract = $player->canInteract($packet->itemInteractionData->blockPos->add(0.5, 0.5, 0.5), $player->isCreative() ? 13 : 7);
				$useBreakOn = $player->getWorld()->useBreakOn($packet->itemInteractionData->blockPos, $item, $player, true);
				if ($canInteract and $useBreakOn) {
					if ($player->isSurvival()) {
						if (!$item->equalsExact($oldItem) and $oldItem->equalsExact($player->getInventory()->getItemInHand())) {
							$player->getInventory()->setItemInHand($item);
						}
					}
				}
			}
		}
	}

	public function onSend(DataPacketSendEvent $event): void {
		$targets = [];
		foreach ($event->getTargets() as $target) {
			$targets[] = "{$target->getIp()} {$target->getPort()}";
		}
		foreach ($event->getPackets() as $packet) {
			if ($packet instanceof StartGamePacket) {
				$packet->playerMovementSettings = new PlayerMovementSettings(
					PlayerMovementType::SERVER_AUTHORITATIVE_V2_REWIND,
					0,
					false
				);
			}
			try {
				Esoteric::getInstance()->thread->queueOutbound($targets, $packet);
			} catch (BadMethodCallException $_) {
				if ($packet instanceof LevelSoundEventPacket) {
					$packet->position = new Vector3($packet->position->x, $packet->position->y, $packet->position->z);
				}
				// TODO: Can other packets cause this?
				Esoteric::getInstance()->thread->queueOutbound($targets, $packet);
			}
		}
	}

}