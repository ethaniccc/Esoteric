<?php

namespace ethaniccc\Esoteric\check\misc\nuker;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerBlockAction;
use pocketmine\block\Air;
use pocketmine\block\BlockIds;
use pocketmine\block\Transparent;
use pocketmine\entity\Effect;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\types\GameMode;

class NukerA extends Check {

	private const NO_START = -1;
	private static $allowBypass;
	private $startBreakTick = -1;
	private $shouldSubtract = false;

	public function __construct() {
		if (self::$allowBypass === null) {
			self::$allowBypass = range(743, 747);
		}
		parent::__construct("Nuker", "A", "...", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket) {
			if ($packet->blockActions === null && $packet->itemInteractionData === null) {
				$this->shouldSubtract = true;
			}
			if ($packet->blockActions !== null) {
				foreach ($packet->blockActions as $blockAction) {
					switch ($blockAction->actionType) {
						case PlayerBlockAction::START_BREAK:
							if ($this->startBreakTick !== self::NO_START) {
								$this->flag($data, ["currSBT" => $this->startBreakTick]);
							}
							$this->startBreakTick = $data->currentTick;
							break;
						case PlayerBlockAction::ABORT_BREAK:
							$this->startBreakTick = self::NO_START;
							break;
					}
				}
			}
			if ($packet->itemInteractionData !== null) {
				$tickDiff = $data->currentTick - $this->startBreakTick;
				$block = $data->blockBroken;
				if ($block !== null && $block->getId() !== BlockIds::AIR && !in_array($packet->itemInteractionData->heldItem->getId(), self::$allowBypass)) {
					// 0.05 seconds in a tick
					$expected = $block->getBreakTime($packet->itemInteractionData->heldItem);
					$hasteEffect = $data->effects[Effect::HASTE] ?? null;
					if ($hasteEffect !== null) {
						// idk how to actually account for haste, just default to a 1 tick difference
						$expected = $hasteEffect->amplifier >= 10 ? 0 : 1;
					}
					$expectedTicks = ceil($expected / 0.05);
					if ($this->shouldSubtract) {
						$expectedTicks = max($expectedTicks - 1, 0);
					}
					if ($expectedTicks > 1 && $this->startBreakTick === self::NO_START && $data->gamemode !== GameMode::CREATIVE && $data->gamemode !== GameMode::CREATIVE_VIEWER) {
						$this->flag($data, ["exp" => $expectedTicks, "start" => "N/A"]);
					}
					if (($diff = $expectedTicks - $tickDiff) > 1 && $data->gamemode !== GameMode::CREATIVE && $data->gamemode !== GameMode::CREATIVE_VIEWER) {
						$this->flag($data, ["ticks" => $tickDiff, "expected" => $expectedTicks, "diff" => $diff]);
					}
				}
				$this->startBreakTick = self::NO_START;
				$this->shouldSubtract = false;
			}
		}
	}

}