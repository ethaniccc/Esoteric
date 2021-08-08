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
use ethaniccc\Esoteric\utils\MovementUtils;
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

	private const THRESHOLD = 1 / 16;

	/** @var Position[] */
	private $safeLocations = [];

	public function __construct() {
		parent::__construct("Phase", "A", "Checks if a player makes an invalid movement into a block", true);
	}

	public function inbound(DataPacket $packet, PlayerData $data): void {
		if ($packet instanceof PlayerAuthInputPacket && $data->loggedIn && !$data->isClipping && !$data->teleported) {
			// only check when the movement is high enough to consider it an advantage
			if ($data->currentMoveDelta->lengthSquared() > 0) {
				$diffVec = MovementUtils::doCollisions($data)->subtract($data->currentLocation);
				$this->debug($data, "diff=$diffVec");
				if ($diffVec->x > self::THRESHOLD || $diffVec->z > self::THRESHOLD) {
					$this->flag($data);
					$this->setback($data);
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

}