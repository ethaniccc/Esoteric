<?php

namespace ethaniccc\Esoteric\tasks;

use ethaniccc\Esoteric\utils\banwave\Banwave;
use pocketmine\scheduler\AsyncTask;
use function file_exists;

class CreateBanwaveTask extends AsyncTask {

	private string $path;
	private Banwave|null $banwave;

	public function __construct(string $path, callable $onCompete = null) {
		$this->path = $path;
		$this->storeLocal('complete', $onCompete);
	}

	public function onRun() : void {
		$this->banwave = Banwave::create($this->path, !file_exists($this->path), true);
	}

	public function onCompletion() : void {
		$onComplete = $this->fetchLocal('complete');
		if ($onComplete !== null) {
			$onComplete($this->banwave);
		}
	}

}