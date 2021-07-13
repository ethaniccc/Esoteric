<?php

namespace ethaniccc\Esoteric;

use pocketmine\utils\TextFormat;
use function in_array;
use function is_null;

final class Settings {

	public const SETBACK_INSTANT = 'instant';
	public const SETBACK_SMOOTH = 'smooth';
	private array $data;
	private ?string $setbackType = null;

	public function __construct(array $configData) {
		$this->data = $configData;
	}

	public function getCheckSettings(string $type, string $subType): ?array {
		return ($this->data['detections'][$type])[$subType] ?? null;
	}

	public function getPrefix(): string {
		return ($this->data['prefix'] ?? '§l§7[§c!§7]') . TextFormat::RESET;
	}

	public function getAlertCooldown(): float {
		return $this->data['alert_cooldown'] ?? 5.0;
	}

	public function getAlertMessage(): string {
		return $this->data['alert_message'];
	}

	public function getBanLength(): string {
		return $this->data['ban_length'];
	}

	public function getSetbackType(): string {
		if (is_null($this->setbackType)) {
			$this->setbackType = isset($this->data['setback_type']) ? (in_array($this->data['setback_type'], [self::SETBACK_INSTANT, self::SETBACK_SMOOTH], true) ? $this->data['setback_type'] : 'none') : self::SETBACK_SMOOTH;
		}
		return $this->setbackType;
	}

	public function getKickMessage(): string {
		return $this->data['kick_message'] ?? "{prefix} Kicked (code={code})\nContact staff if this issue persists";
	}

	public function getKickBroadcast() : string {
		return $this->data['kick_broadcast'];
	}

	public function getBanMessage(): string {
		return $this->data['ban_message'] ?? "{prefix} Banned (code={code})\nMake a ticket if this is a mistake";
	}

	public function getBanBroadcast() : string {
		return $this->data['ban_broadcast'];
	}

	public function getWebhookSettings(): array {
		return $this->data['webhook'] ?? ['link' => 'none', 'alerts' => false, 'punishments' => false];
	}

	public function getWaveSettings(): array {
		return $this->data['banwaves'] ?? ['enabled' => false, 'violations' => -1];
	}

	public function getTimeoutSettings(): array {
		return $this->data['timeout'] ?? ['enabled' => true, 'total_packets' => 10, 'ticks' => 10];
	}

	public function editSetting(string $key, $newVal): void {
		$this->data[$key] = $newVal;
	}

}