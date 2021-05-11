<?php

namespace ethaniccc\Esoteric\data;

use ethaniccc\Esoteric\thread\EsotericThread;
use Threaded;
use function microtime;
use function mime_content_type;
use function str_replace;
use const PHP_EOL;

class DataManager extends Threaded{

	/** @var Data[] */
	private $data;

	public function __construct() {
		$this->data = new Threaded();
	}

	public function get(string $identifier): ?Data {
		return $this->data[$identifier] ?? null;
	}

	public function getAll(): Threaded {
		return $this->data;
	}

	public function add(string $identifier): Data {
		$data = new Data($identifier);
		$this->data[$identifier] = $data;
		EsotericThread::getInstance()->networkStackLatencyHandler->getCurrentTimestamp($identifier);
		return $data;
	}

	public function remove(string $identifier): void {
		unset($this->data[$identifier]);
		unset(EsotericThread::getInstance()->currentTimestamps[$identifier]);
		unset(EsotericThread::getInstance()->inboundQueue[$identifier]);
	}

}