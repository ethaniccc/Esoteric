<?php

namespace CortexPE\DiscordWebhookAPI;

use pocketmine\Server;
use pocketmine\Thread;
use Threaded;

class WebhookThread extends Thread {

	private static $instance;

	/** @var Threaded */
	private $queue, $errors;
	private $running = false;

	public static function valid(): bool {
		return self::$instance !== null;
	}

	/**
	 * @param bool $shouldStart
	 * @throws \Exception
	 */
	public static function init(bool $shouldStart = true): void {
		if (self::$instance !== null) {
			throw new \Exception("WebhookThread is already initialized");
		}
		self::$instance = new self();
		self::$instance->queue = new Threaded();
		self::$instance->errors = new Threaded();
		self::$instance->running = true;
		self::$instance->setClassLoader(Server::getInstance()->getLoader());
		if ($shouldStart) {
			self::$instance->start(PTHREADS_INHERIT_NONE);
		}
	}

	public static function getInstance(): ?self {
		return self::$instance;
	}

	public function run() {
		while($this->running) {
			while (($webhook = $this->queue->shift()) !== null) {
				/** @var Webhook $webhook */
				$ch = curl_init($this->webhook->getURL());
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook->getMessage()));
				curl_setopt($ch, CURLOPT_POST,true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
				if (!in_array(($responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)), [200, 204])) {
					$this->errors[] = "Got error ($responseCode) while sending Webhook - " . curl_exec($ch);
				}
				curl_close($ch);
				usleep(25000);
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

}