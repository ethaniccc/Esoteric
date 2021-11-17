<?php

namespace ethaniccc\Esoteric\check\misc\nuker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\block\BlockLegacyIds as BlockIds;
use pocketmine\data\bedrock\EffectIds;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\ItemFactory;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\network\mcpe\protocol\types\PlayerAction;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\PlayerBlockActionWithBlockInfo;
use function ceil;
use function in_array;
use function max;
use function range;

class NukerA extends Check{

	private const NO_START = -1;
	private static $allowBypass;
	private $startBreakTick = -1;
	private $shouldSubtract = false;

	public function __construct(){
		if(self::$allowBypass === null){
			self::$allowBypass = range(743, 747);
		}
		parent::__construct("Nuker", "A", "...", true);
	}

	public function inbound(ServerboundPacket $packet, PlayerData $data) : void{
		if($packet instanceof PlayerAuthInputPacket){
			if($packet->hasFlag(PlayerAuthInputFlags::PERFORM_BLOCK_ACTIONS)){
				if($packet->getBlockActions() === null && !$packet->hasFlag(PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION)){
					$this->shouldSubtract = true;
				}
				if($packet->getBlockActions() !== null){
					foreach($packet->getBlockActions() as $blockAction){
						if($blockAction instanceof PlayerBlockActionWithBlockInfo){
							switch($blockAction->getActionType()){
								case PlayerAction::START_BREAK:
									if($this->startBreakTick !== self::NO_START){
										$this->flag($data, ["currSBT" => $this->startBreakTick]);
									}
									$this->startBreakTick = $data->currentTick;
									break;
								case PlayerAction::ABORT_BREAK:
									$this->startBreakTick = self::NO_START;
									break;
							}
						}
					}
				}
			}
			if($packet->hasFlag(PlayerAuthInputFlags::PERFORM_ITEM_INTERACTION)){
				$tickDiff = $data->currentTick - $this->startBreakTick;
				$block = $data->blockBroken;
				$item = TypeConverter::getInstance()->netItemStackToCore($packet->itemInteractionData->getTransactionData()->getItemInHand()->getItemStack());
				if($block !== null && $block->getId() !== BlockIds::AIR && !in_array($item->getId(), self::$allowBypass, true)){
					// 0.05 seconds in a tick
					$stack = $item;
					$expected = $block->getBreakInfo()->getBreakTime(ItemFactory::getInstance()->get($stack->getId(), $stack->getMeta()));
					$hasteEffect = $data->effects[EffectIds::HASTE] ?? null;
					if($hasteEffect !== null){
						// idk how to actually account for haste, just default to a 1 tick difference
						$expected = $hasteEffect->amplifier >= 10 ? 0 : 1;
					}
					$expectedTicks = ceil($expected / 0.05);
					if($this->shouldSubtract){
						$expectedTicks = max($expectedTicks - 1, 0);
					}
					if($expectedTicks > 1 && $this->startBreakTick === self::NO_START && $data->gamemode !== GameMode::CREATIVE && $data->gamemode !== GameMode::CREATIVE_VIEWER){
						$this->flag($data, ["exp" => $expectedTicks, "start" => "N/A"]);
					}
					if(($diff = $expectedTicks - $tickDiff) > 1 && $data->gamemode !== GameMode::CREATIVE && $data->gamemode !== GameMode::CREATIVE_VIEWER){
						$this->flag($data, ["ticks" => $tickDiff, "expected" => $expectedTicks, "diff" => $diff]);
					}
				}
				$this->startBreakTick = self::NO_START;
				$this->shouldSubtract = false;
			}
		}
	}

}