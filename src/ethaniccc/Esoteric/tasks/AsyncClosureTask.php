<?php

namespace ethaniccc\Esoteric\tasks;

use pocketmine\scheduler\AsyncTask;

class AsyncClosureTask extends AsyncTask {

	private $onRun;

	public function __construct(callable $onRun, callable $onComplete = null) {
		$this->onRun = $onRun;
		$this->storeLocal('complete', $onComplete);
	}

	public function onRun() : void {
		($this->onRun)();
	}

	public function onCompletion() : void {
		$onComplete = $this->fetchLocal('complete');
		if ($onComplete !== null) {
			$onComplete();
		}
	}

}