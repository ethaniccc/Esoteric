<?php

namespace ethaniccc\Esoteric\data\sub\protocol\v428;

use ethaniccc\Esoteric\data\sub\protocol\LegacyItemSlot;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\NetworkInventoryAction;

class UseItemInteractionData{

    /** @var int */
    public $legacyRequestId;
    /** @var LegacyItemSlot[] */
    public $legacyItemSlots;
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
    /** @var Item */
    public $heldItem;
    /** @var Vector3 */
    public $playerPos;
    /** @var Vector3 */
    public $clickPos;
    /** @var int */
    public $blockRuntimeId;


}