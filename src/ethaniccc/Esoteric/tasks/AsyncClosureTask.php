<?php

namespace ethaniccc\Esoteric\tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class AsyncClosureTask extends AsyncTask {

	private $onRun;

	public function __construct(callable $onRun, callable $onComplete = null) {
		$this->onRun = $onRun;
		$this->storeLocal($onComplete);
	}

	public function onRun(): void {
		($this->onRun)();
	}

	public function onCompletion(Server $server): void {
		$onComplete = $this->fetchLocal();
		if ($onComplete !== null) {
			$onComplete();
		}
	}

}