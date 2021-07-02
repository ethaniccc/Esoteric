<?php

namespace ethaniccc\Esoteric\data\sub\protocol;

use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;

final class InputConstants {

	public const ASCEND = 0;
	public const DESCEND = 1;
	public const NORTH_JUMP = 2;
	public const JUMP_DOWN = 3;
	public const SPRINT_DOWN = 4;
	public const CHANGE_HEIGHT = 5;
	public const JUMPING = 6;
	public const AUTO_JUMPING_IN_WATER = 7;
	public const SNEAKING = 8;
	public const SNEAK_DOWN = 9;
	public const UP = 10;
	public const DOWN = 11;
	public const LEFT = 12;
	public const RIGHT = 13;
	public const UP_LEFT = 14;
	public const UP_RIGHT = 15;
	public const WANT_UP = 16;
	public const WANT_DOWN = 17;
	public const WANT_DOWN_SLOW = 18;
	public const WANT_UP_SLOW = 19;
	public const SPRINTING = 20;
	public const ASCEND_SCAFFOLDING = 21;
	public const DESCEND_SCAFFOLDING = 22;
	public const SNEAK_TOGGLE_DOWN = 23;
	public const PERSIST_SNEAK = 24;
	// player actions (why the fuck did Mojang decide to put these in PlayerAuthInput???)
	public const START_SPRINTING = 25;
	public const STOP_SPRINTING = 26;
	public const START_SNEAKING = 27;
	public const STOP_SNEAKING = 28;
	public const START_SWIMMING = 29;
	public const STOP_SWIMMING = 30;
	public const START_JUMPING = 31;
	public const START_GLIDING = 32;
	public const STOP_GLIDING = 33;
	public const PERFORM_ITEM_INTERACTION = 34;
	public const PERFORM_BLOCK_ACTIONS = 35;
	public const PERFORM_ITEM_STACK_REQUEST = 36;

	public static function hasFlag(PlayerAuthInputPacket $packet, ...$flags): bool {
		foreach ($flags as $flag) {
			if (($packet->getInputFlags() & (1 << $flag)) !== 0) {
				return true;
			}
		}
		return false;
	}

}