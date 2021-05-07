<?php

namespace ethaniccc\Esoteric\protocol\v428;

use ethaniccc\Esoteric\protocol\LegacyItemSlot;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\NetworkInventoryAction;

class UseItemInteractionData {

	/** @var int */
	public $legacyRequestId;
	/** @var LegacyItemSlot[] */
	public $legacyItemSlots = [];
	/** @var bool */
	public $hasNetworkIds;
	/** @var NetworkInventoryAction[] */
	public $actions;
	/** @var int */
	public $actionType;
	/** @var Vector3 */
	public $blockPos;
	/** @var int */
	public $blockFace;
	/** @var int */
	public $hotbarSlot;
	/** @var ItemStack */
	public $heldItem;
	/** @var Vector3 */
	public $playerPos;
	/** @var Vector3 */
	public $clickPos;
	/** @var int */
	public $blockRuntimeId;


}