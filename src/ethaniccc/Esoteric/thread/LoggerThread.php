<?php

namespace ethaniccc\Esoteric\thread;

use pocketmine\Thread;
use pocketmine\utils\AssumptionFailedError;
use Threaded;
use function fclose;
use function fwrite;
use function is_resource;

class LoggerThread extends Thread {

	private $log;
	private $buffer;
	private $running = false;

	public function __construct(string $log) {
		$this->log = $log;
		$this->buffer = new Threaded();
	}

	public function start(int $options = PTHREADS_INHERIT_ALL): bool {
		$this->running = true;
		return parent::start($options);
	}

	public function run(): void {
		$stream = fopen($this->log, 'ab');
		if (!is_resource($stream)) {
			throw new AssumptionFailedError("Open File $this->log failed");
		}
		while ($this->running) {
			$this->writeStream($stream);
			$this->synchronized(function () {
				if ($this->running) {
					$this->wait();
				}
			});
		}
		$this->writeStream($stream);
		fclose($stream);
	}

	private function writeStream($stream): void {
		while ($this->buffer->count() > 0) {
			/** @var string $line */
			$line = $this->buffer->pop();
			fwrite($stream, $line . "\n");
		}
	}

	public function quit(): void {
		$this->running = false;
		parent::quit();
	}

	public function write(string $data): void {
		$this->buffer[] = $data;
		$this->notify();
	}

}