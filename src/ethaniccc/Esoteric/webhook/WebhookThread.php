<?php

namespace ethaniccc\Esoteric\webhook;

use AttachableLogger;
use Exception;
use pocketmine\Server;
use Threaded;

class WebhookThread extends Thread {

	private static $instance;

	/** @var Threaded */
	private $queue, $errors;
	private $running = false;
	/** @var AttachableLogger */
	private $logger;

	public static function valid(): bool {
		return self::$instance !== null;
	}

	/**
	 * @param bool $shouldStart
	 * @throws Exception
	 */
	public static function init(bool $shouldStart = true): void {
		if (self::$instance !== null) {
			throw new Exception("WebhookThread is already initialized");
		}
		self::$instance = new self();
		self::$instance->queue = new Threaded();
		self::$instance->errors = new Threaded();
		self::$instance->running = true;
		self::$instance->setClassLoader(Server::getInstance()->getLoader());
		self::$instance->logger = Server::getInstance()->getLogger();
		if ($shouldStart) {
			self::$instance->start(PTHREADS_INHERIT_NONE);
		}
	}

	public static function getInstance(): ?self {
		return self::$instance;
	}

	public function run() {
		$this->registerClassLoader();
		while ($this->running) {
			while (($webhook = $this->queue->shift()) !== null) {
				/** @var Webhook $webhook */
				$ch = curl_init($webhook->getURL());
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook->getMessage()));
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
				$responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
				$err = curl_exec($ch);
				if (!in_array($responseCode, [0, 200, 204])) {
					$this->errors[] = ($error = "Got error ($responseCode) while sending Webhook - $err");
					$this->logger->debug("($responseCode) $error");
				}
				curl_close($ch);
				if ($this->running) {
					usleep(500000);
				}
			}
			usleep(50000);
		}
	}

	public function stop(): void {
		$this->running = false;
	}

	public function queue(Webhook $webhook): void {
		$this->queue[] = $webhook;
	}

	public function getErrors(): Threaded {
		return $this->errors;
	}

}