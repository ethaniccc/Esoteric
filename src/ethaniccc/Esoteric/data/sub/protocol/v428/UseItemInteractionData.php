<?php

namespace ethaniccc\Esoteric\data\sub\protocol\v428;

use ethaniccc\Esoteric\data\sub\protocol\LegacyItemSlot;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;

class UseItemInteractionData {

	/** @var int */
	public int $legacyRequestId;
	/** @var LegacyItemSlot[] */
	public array $legacyItemSlots = [];
	/** @var bool */
	public bool $hasNetworkIds;
	/** @var NetworkInventoryAction[] */
	public array $actions;
	/** @var int */
	public int $actionType;
	/** @var Vector3 */
	public Vector3 $blockPos;
	/** @var int */
	public int $blockFace;
	/** @var int */
	public int $hotbarSlot;
	/** @var ItemStack */
	public ItemStack $heldItem;
	/** @var Vector3 */
	public Vector3 $playerPos;
	/** @var Vector3 */
	public Vector3 $clickPos;
	/** @var int */
	public int $blockRuntimeId;

}