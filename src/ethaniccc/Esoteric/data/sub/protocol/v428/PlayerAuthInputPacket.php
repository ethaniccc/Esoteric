<?php

namespace ethaniccc\Esoteric\data\sub\protocol\v428;

use ethaniccc\Esoteric\data\sub\protocol\InputConstants;
use ethaniccc\Esoteric\data\sub\protocol\LegacyItemSlot;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\PlayMode;

class PlayerAuthInputPacket extends \pocketmine\network\mcpe\protocol\PlayerAuthInputPacket {

	public ?UseItemInteractionData $itemInteractionData;
	public ?ItemStackRequest $stackRequest;
	/** @var PlayerBlockAction[]|null */
	public ?array $blockActions;
	private Vector3 $position;
	private float $pitch;
	private float $yaw;
	private float $headYaw;
	private float $moveVecX;
	private float $moveVecZ;
	private int $inputFlags;
	private int $inputMode;
	private int $playMode;
	private ?Vector3 $vrGazeDirection = null;
	private int $tick;
	private Vector3 $delta;

	public function getPosition(): Vector3 {
		return $this->position;
	}

	public function getPitch(): float {
		return $this->pitch;
	}

	public function getYaw(): float {
		return $this->yaw;
	}

	public function getHeadYaw(): float {
		return $this->headYaw;
	}

	public function getMoveVecX(): float {
		return $this->moveVecX;
	}

	public function getMoveVecZ(): float {
		return $this->moveVecZ;
	}

	/**
	 * @see PlayerAuthInputFlags
	 */
	public function getInputFlags(): int {
		return $this->inputFlags;
	}

	/**
	 * @see InputMode
	 */
	public function getInputMode(): int {
		return $this->inputMode;
	}

	/**
	 * @see PlayMode
	 */
	public function getPlayMode(): int {
		return $this->playMode;
	}

	public function getVrGazeDirection(): ?Vector3 {
		return $this->vrGazeDirection;
	}

	public function getTick(): int {
		return $this->tick;
	}

	public function getDelta(): Vector3 {
		return $this->delta;
	}

	protected function decodePayload(PacketSerializer $in): void {
		$this->pitch = $in->getLFloat();
		$this->yaw = $in->getLFloat();
		$this->position = $in->getVector3();
		$this->moveVecX = $in->getLFloat();
		$this->moveVecZ = $in->getLFloat();
		$this->headYaw = $in->getLFloat();
		$this->inputFlags = $in->getUnsignedVarLong();
		$this->inputMode = $in->getUnsignedVarInt();
		$this->playMode = $in->getUnsignedVarInt();
		if ($this->playMode === PlayMode::VR) {
			$this->vrGazeDirection = $in->getVector3();
		}
		$this->tick = $in->getUnsignedVarLong();
		$this->delta = $in->getVector3();
		if (InputConstants::hasFlag($this, InputConstants::PERFORM_ITEM_INTERACTION)) {
			$this->itemInteractionData = new UseItemInteractionData();
			$this->itemInteractionData->legacyRequestId = $in->getVarInt();
			if ($this->itemInteractionData->legacyRequestId !== 0) {
				$k = $in->getUnsignedVarInt();
				for ($i = 0; $i < $k; ++$i) {
					$sl = new LegacyItemSlot();
					$sl->containerId = $in->getByte();
					$sl->slots = $in->getString();
					$this->itemInteractionData->legacyItemSlots[] = $sl;
				}
			}
			$l = $in->getUnsignedVarInt();
			for ($i = 0; $i < $l; ++$i) {
				$this->itemInteractionData->actions[] = (new NetworkInventoryAction())->read($in);
			}
			$this->itemInteractionData->actionType = $in->getUnsignedVarInt();
			$x = $y = $z = 0;
			$in->getBlockPosition($x, $y, $z);
			$this->itemInteractionData->blockPos = new Vector3($x, $y, $z);
			$this->itemInteractionData->blockFace = $in->getVarInt();
			$this->itemInteractionData->hotbarSlot = $in->getVarInt();
			$this->itemInteractionData->heldItem = ItemStackWrapper::read($in)->getItemStack();
			$this->itemInteractionData->playerPos = $in->getVector3();
			$this->itemInteractionData->clickPos = $in->getVector3();
			$this->itemInteractionData->blockRuntimeId = $in->getUnsignedVarInt();
		}
		if (InputConstants::hasFlag($this, InputConstants::PERFORM_ITEM_STACK_REQUEST)) {
			$this->stackRequest = ItemStackRequest::read($in);
		}
		if (InputConstants::hasFlag($this, InputConstants::PERFORM_BLOCK_ACTIONS)) {
			$max = $in->getVarInt();
			for ($i = 0; $i < $max; ++$i) {
				$action = new PlayerBlockAction();
				$action->actionType = $in->getVarInt();
				switch ($action->actionType) {
					case PlayerBlockAction::ABORT_BREAK:
					case PlayerBlockAction::START_BREAK:
					case PlayerBlockAction::CRACK_BREAK:
					case PlayerBlockAction::PREDICT_DESTROY:
					case PlayerBlockAction::CONTINUE:
						$action->blockPos = new Vector3($in->getVarInt(), $in->getVarInt(), $in->getVarInt());
						$action->face = $in->getVarInt();
						break;
				}
				$this->blockActions[] = $action;
			}
		}
	}

}