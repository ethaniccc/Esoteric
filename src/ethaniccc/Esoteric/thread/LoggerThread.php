<?php

namespace ethaniccc\Esoteric\thread;

use pocketmine\thread\Thread;
use Threaded;
use function fclose;
use function fwrite;
use function usleep;
use const PHP_EOL;

class LoggerThread extends Thread {

	private string $log;
	private Threaded $queue;
	private bool $running = false;

	public function __construct(string $log) {
		$this->log = $log;
		$this->queue = new Threaded();
	}

	public function start(int $options = PTHREADS_INHERIT_NONE): bool {
		$this->running = true;
		return parent::start($options);
	}

	public function quit(): void {
		$this->running = false;
		parent::quit();
	}

	public function write(string $data): void {
		$this->queue[] = $data . PHP_EOL;
	}

	protected function onRun(): void {
		while ($this->running) {
			$count = 0;
			$log = fopen($this->log, "a");
			while (($data = $this->queue->shift()) !== null) {
				$count++;
				fwrite($log, $data);
			}
			fclose($log);
			if ($count === 0) {
				usleep(100000);
			}
		}
	}
}