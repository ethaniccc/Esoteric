<?php

namespace ethaniccc\Esoteric\utils\handler;

use ethaniccc\Esoteric\data\PlayerData;
use pocketmine\utils\TextFormat;
use function count;

final class DebugHandler {

	/** @var string */
	private $name;
	/** @var PlayerData[] */
	private $targets = [];

	public function __construct(string $name) {
		$this->name = $name;
	}

	public function add(PlayerData $data): void {
		$this->targets[$data->networkIdentifier] = $data;
		$this->broadcast("{$data->player->getName()} was added to this debug handler");
	}

	public function remove(PlayerData $data): void {
		$wasSet = isset($this->targets[$data->networkIdentifier]);
		unset($this->targets[$data->networkIdentifier]);
		if ($wasSet) {
			$this->broadcast("{$data->player->getName()} was removed from this debug handler");
		}
	}

	public function getName(): string {
		return $this->name;
	}

	public function broadcast(string $message): void {
		if (count($this->targets) === 0) {
			return;
		}
		$this->updateTargets();
		foreach ($this->targets as $target) {
			if ($target->loggedIn) {
				$target->player->sendMessage(TextFormat::GRAY . "[" . TextFormat::RED . "DEBUG" . TextFormat::GRAY . " @ " . TextFormat::YELLOW . $this->name . TextFormat::GRAY . "] " . TextFormat::WHITE . $message);
			}
		}
	}

	private function updateTargets(): void {
		$toRemove = [];
		foreach ($this->targets as $k => $target) {
			if ($target->isDataClosed) {
				$toRemove[] = $k;
			}
			unset($target);
		}
		foreach ($toRemove as $key) {
			unset($this->targets[$key]);
		}
	}

}