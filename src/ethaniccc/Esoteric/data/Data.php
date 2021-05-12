<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\data\sub\world\VirtualWorld;
use ethaniccc\Esoteric\handlers\InboundHandler;
use ethaniccc\Esoteric\handlers\OutboundHandler;
use ethaniccc\Esoteric\thread\EsotericThread;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\utils\TextFormat;
use Threaded;

final class Data extends Threaded {

	/** @var string - An identifier for the data, "ip port" */
	public $identifier;

	/** @var int - Current client tick */
	public $currentTick = 0;
	/** @var bool - Boolean value of wether or not the player is logged in. */
	public $loggedIn = false;

	/** @var VirtualWorld */
	public $world;
	/** @var int */
	public $currentChunkHash = -111;
	/** @var Vector3 */
	public $currentPosition, $lastPosition;
	/** @var Vector3 */
	public $currentMoveDelta, $lastMoveDelta;

	/** @var Threaded */
	public $clickSamples;
	/** @var int - Clicks per second */
	public $clicksPerSecond = 0;

	/** @var InboundHandler */
	public $inboundHandler;
	/** @var OutboundHandler */
	public $outboundHandler;

	public function __construct(string $identifier) {
		$this->identifier = $identifier;
		$this->world = new VirtualWorld();

		$zero = new Vector3(0, 0, 0);
		$this->currentMoveDelta = $zero;
		$this->lastMoveDelta = $zero;
		$this->currentPosition = $zero;
		$this->lastPosition = $zero;

		$this->clickSamples = new Threaded();

		$this->inboundHandler = new InboundHandler();
		$this->outboundHandler = new OutboundHandler();
	}

	public function queueDebugMessageSend(string $message): void {
		EsotericThread::getInstance()->queuePacket(TextPacket::raw(TextFormat::BOLD . TextFormat::DARK_GRAY . "[" . TextFormat::DARK_RED . "DEBUG" . TextFormat::DARK_GRAY . "] " . TextFormat::RESET . $message), $this->identifier);
	}

}