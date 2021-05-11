<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\handlers\InboundHandler;
use ethaniccc\Esoteric\handlers\OutboundHandler;
use pocketmine\math\Vector3;
use Threaded;

final class Data extends Threaded {

	/** @var string - An identifier for the data, "ip port" */
	public $identifier;

	/** @var int - Current client tick */
	public $currentTick = 0;

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
		$zero = new Vector3(0, 0, 0);
		$this->currentMoveDelta = $zero;
		$this->lastMoveDelta = $zero;
		$this->currentPosition = $zero;
		$this->lastPosition = $zero;

		$this->clickSamples = new Threaded();

		$this->inboundHandler = new InboundHandler();
		$this->outboundHandler = new OutboundHandler();
	}

}