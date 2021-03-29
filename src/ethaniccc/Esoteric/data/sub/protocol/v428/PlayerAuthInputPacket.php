<?php

namespace ethaniccc\Esoteric\data\sub\protocol\v428;

use ethaniccc\Esoteric\data\sub\protocol\InputConstants;
use ethaniccc\Esoteric\data\sub\protocol\LegacyItemSlot;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\inventory\stackrequest\ItemStackRequest;
use pocketmine\network\mcpe\protocol\types\NetworkInventoryAction;

class PlayerAuthInputPacket extends \pocketmine\network\mcpe\protocol\PlayerAuthInputPacket{

    public static function from(\pocketmine\network\mcpe\protocol\PlayerAuthInputPacket $packet) : self{
        $self = new self($packet->getBuffer());
        $self->decode();
        return $self;
    }

    /** @var UseItemInteractionData|null */
    public $itemInteractionData;
    /** @var ItemStackRequest|null */
    public $stackRequest;
    /** @var PlayerBlockAction[]|null */
    public $blockActions;

    protected function decodePayload(): void{
        parent::decodePayload();
        if(InputConstants::hasFlag($this, InputConstants::PERFORM_ITEM_INTERACTION)){
            $this->itemInteractionData = new UseItemInteractionData();
            $this->itemInteractionData->legacyRequestId = $this->getVarInt();
            if($this->itemInteractionData->legacyRequestId !== 0){
                $l = $this->getVarInt();
                for($i = 0; $i < $l; ++$i){
                    $sl = new LegacyItemSlot();
                    $sl->containerId = $this->getByte();
                    $sl->slots = $this->getString();
                    $this->itemInteractionData->legacyItemSlots[] = $sl;
                }
            }
            $this->itemInteractionData->hasNetworkIds = $this->getBool();
            $l = $this->getVarInt();
            for($i = 0; $i < $l; ++$i){
                $this->itemInteractionData->actions[] = (new NetworkInventoryAction())->read($this, $this->itemInteractionData->hasNetworkIds);
            }
            $this->itemInteractionData->actionType = $this->getVarInt();
            $x = $y = $z = 0;
            $this->getBlockPosition($x, $y, $z);
            $this->itemInteractionData->blockPos = new Vector3($x, $y, $z);
            $this->itemInteractionData->blockFace = $this->getVarInt();
            $this->itemInteractionData->hotbarSlot = $this->getVarInt();
            $this->itemInteractionData->heldItem = $this->getSlot();
            $this->itemInteractionData->playerPos = $this->getVector3();
            $this->itemInteractionData->clickPos = $this->getVector3();
            $this->itemInteractionData->blockRuntimeId = $this->getVarInt();
        }
        if(InputConstants::hasFlag($this, InputConstants::PERFORM_ITEM_STACK_REQUEST)){
            $this->stackRequest = ItemStackRequest::read($this);
        }
        if(InputConstants::hasFlag($this, InputConstants::PERFORM_BLOCK_ACTIONS)){
            $max = $this->getVarInt();
            for($i = 0; $i < $max; ++$i){
                $action = new PlayerBlockAction();
                $action->action = $this->getVarInt();
                switch($action->action){
                    case PlayerBlockAction::ABORT_BREAK:
                    case PlayerBlockAction::START_BREAK:
                    case PlayerBlockAction::CRACK_BREAK:
                    case PlayerBlockAction::PREDICT_DESTROY:
                    case PlayerBlockAction::CONTINUE:
                        $action->blockPos = new Vector3($this->getVarInt(), $this->getVarInt(), $this->getVarInt());
                        $action->face = $this->getVarInt();
                        break;
                }
                $this->blockActions[] = $action;
            }
        }
    }

}