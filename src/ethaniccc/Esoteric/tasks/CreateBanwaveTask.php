<?php

namespace ethaniccc\Esoteric\tasks;

use ethaniccc\Esoteric\utils\banwave\Banwave;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use function file_exists;

class CreateBanwaveTask extends AsyncTask {

	private $path;
	private $banwave;

	public function __construct(string $path, callable $onCompete = null) {
		$this->path = $path;
		$this->storeLocal($onCompete);
	}

	public function onRun(): void {
		$this->banwave = Banwave::create($this->path, !file_exists($this->path), true);
	}

	public function onCompletion(Server $server): void {
		$onComplete = $this->fetchLocal();
		if ($onComplete !== null) {
			$onComplete($this->banwave);
		}
	}

}