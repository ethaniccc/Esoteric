<?php

namespace ethaniccc\Esoteric\check\movement\phase;

use ethaniccc\Esoteric\check\Check;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\process\NetworkStackLatencyHandler;
use ethaniccc\Esoteric\data\sub\movement\MovementConstants;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\Settings;
use ethaniccc\Esoteric\utils\LevelUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\block\Fallable;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use function array_unique;
use function count;

class PhaseA extends Check {

	private const EXPAND_LENIENCY = 2;
	private const EXEMPT_LIST = ["Stair", "Slab", "Carpet"];

	/** @var Block[] */
	private $ignoreUpdates = [];
	/** @var Position[] */
	private $safeLocations = [];

	public function __construct() {
		parent::__construct("Phase", "A", "Checks if a player makes an invalid movement into a block", true);
		$this->fallbackSetback = true;
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket && $data->loggedIn) {
			// only check when the movement is high enough to consider it an advantage
			if ($data->currentMoveDelta->lengthSquared() > 0) {
				$ignoreBB = $data->boundingBox->expandedCopy(self::EXPAND_LENIENCY, self::EXPAND_LENIENCY, self::EXPAND_LENIENCY);
				foreach ($this->ignoreUpdates as $k => $block) {
					if ($data->world->getBlock($block)->getId() !== $block->getId()) {
						unset($this->ignoreUpdates[$k]);
						continue;
					}
					if (!$ignoreBB->isVectorInside($block)) {
						unset($this->ignoreUpdates[$k]);
					} else {
						// the player is inside a position we are still ignoring, no point in further checking.
						// TODO: There probably is a better way to do this. jUsT uSe prEdIcTiOn bRo
						return;
					}
				}
				$blocks = [];
				$collisionBB = $data->boundingBox->expandedCopy(-0.1, -MovementConstants::GROUND_MODULO, -0.1);
				foreach (LevelUtils::checkBlocksInAABB($collisionBB, $data->world, LevelUtils::SEARCH_SOLID) as $block) {
					/** @var Block $block */
					if ($block->collidesWithBB($collisionBB) && ($boxes = $block->getCollisionBoxes()) !== null && count($boxes) > 0) {
						$blocks[] = $block->getName();
					}
				}
				$blocks = array_unique($blocks);
				if (($count = count($blocks)) > 0) {
					if (!$data->teleported && !$data->isClipping) {
						foreach ($blocks as $block) {
							foreach (self::EXEMPT_LIST as $exemptName) {
								if (strpos($block, $exemptName) !== false) { // TODO: Find out the reason why the player clips through blocks while stepping, and make a GOOD way to compensate for it.
									return;
								}
							}
						}
						//$diff = $data->currentLocation->distanceSquared(MovementUtils::doCollisions($data));
						$this->flag($data, ["count" => $count, "blocks" => implode(",", $blocks)]);
						$this->setback($data);
					}
				} else {
					$this->safeLocations[] = Position::fromObject($data->currentLocation, $data->player->getLevel());
					if (count($this->safeLocations) > 10) {
						array_shift($this->safeLocations);
					}
				}
			}
		}
	}

	public function setback(PlayerData $data): void {
		if (!$data->hasMovementSuppressed && $this->option("setback", $this->fallbackSetback)) {
			$type = Esoteric::getInstance()->getSettings()->getSetbackType();
			switch ($type) {
				case Settings::SETBACK_SMOOTH:
					break;
				case Settings::SETBACK_INSTANT:
					$position = $this->safeLocations[0] ?? null;
					if ($position !== null && $position->level->getId() === $data->player->getLevel()->getId() && $data->world->getBlock($position->add(0, MovementConstants::GROUND_MODULO))->getId() === BlockIds::AIR) {
						$data->player->teleport($position, $data->currentYaw, 0);
					}
					break;
			}
			$data->hasMovementSuppressed = true;
		}
	}

	public function outbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof UpdateBlockPacket) {
			$blockPos = new Vector3($packet->x, $packet->y, $packet->z);
			$original = $data->world->getBlock($blockPos);
			NetworkStackLatencyHandler::getInstance()->send($data, function (int $timestamp) use ($data, $blockPos, $original): void {
				if ($data->boundingBox === null) {
					return;
				}
				$block = $data->world->getBlock($blockPos);
				$key = "{$blockPos->x}:{$blockPos->y}:{$blockPos->z}";
				if ((($block->isTransparent() && $block->isSolid()) || ($block instanceof Fallable && $original->isTransparent())) && $block->getId() !== BlockIds::AIR && $data->boundingBox->expandedCopy(self::EXPAND_LENIENCY, self::EXPAND_LENIENCY, self::EXPAND_LENIENCY)->isVectorInside($blockPos)) {
					$this->ignoreUpdates[$key] = $block;
				} elseif (isset($this->ignoreUpdates[$key]) && $this->ignoreUpdates[$key]->getId() !== $block->getId()) {
					unset($this->ignoreUpdates[$key]);
				}
			});
		}
	}

	public function handleOut(): bool {
		return true;
	}

}