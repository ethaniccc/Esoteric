<?php

namespace ethaniccc\Esoteric\webhook;

use AttachableThreadedLogger;
use pocketmine\Server;
use pocketmine\thread\Thread;
use Threaded;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function in_array;
use function json_encode;
use function usleep;

class WebhookThread extends Thread {

	private static ?WebhookThread $instance = null;

	private Threaded $errors;
	private Threaded $queue;
	private bool $running = false;
	private AttachableThreadedLogger $logger;

	public static function valid(): bool {
		return self::$instance !== null;
	}

	public static function init(bool $shouldStart = true): void {
		self::$instance = new self();
		self::$instance->queue = new Threaded();
		self::$instance->errors = new Threaded();
		self::$instance->running = true;
		self::$instance->setClassLoaders();
		self::$instance->logger = Server::getInstance()->getLogger();
		if ($shouldStart) {
			self::$instance->start(PTHREADS_INHERIT_NONE);
		}
	}

	public static function getInstance(): ?self {
		return self::$instance;
	}

	public function onRun() : void {
		$this->registerClassLoaders();
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