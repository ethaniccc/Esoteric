<?php


namespace ethaniccc\Esoteric\check;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\Settings;
use ethaniccc\Esoteric\tasks\BanTask;
use ethaniccc\Esoteric\tasks\KickTask;
use Exception;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\TextFormat as C;

abstract class Check {

	public static $settings = [];
	public $name;
	public $subType;
	public $description;
	public $experimental;
	public $violations = 0;
	public $buffer = 0;

	public function __construct(string $name, string $subType, string $description, bool $experimental = false) {
		$this->name = $name;
		$this->subType = $subType;
		$this->description = $description;
		$this->experimental = $experimental;
		if (!isset(self::$settings["$name:$subType"])) {
			$settings = Esoteric::getInstance()->getSettings()->getCheckSettings($name, $subType);
			if ($settings === null) {
				$settings = ["enabled" => true, "punishment_type" => "none", "max_vl" => 20];
			}
			self::$settings["$name:$subType"] = $settings;
		}
	}

	public function getData(): array {
		return ["violations" => $this->violations, "description" => $this->description, "full_name" => $this->name . " ({$this->subType})", "name" => $this->name, "subType" => $this->subType];
	}

	public abstract function inbound(DataPacket $packet, PlayerData $data): void;

	public function outbound(DataPacket $packet, PlayerData $data): void {
	}

	public function handleOut(): bool {
		return false;
	}

	public function enabled(): bool {
		return $this->option("enabled");
	}

	protected function option(string $option, $default = null) {
		return self::$settings["{$this->name}:{$this->subType}"][$option] ?? $default;
	}

	protected function flag(PlayerData $data, array $extraData = []): void {
		if (!$this->experimental)
			++$this->violations;
		$extraData["ping"] = $data->player->getPing();
		$this->warn($data, $extraData);
		$this->punish($data);
		return;
		if ($this->violations >= $this->option("max_vl") && $this->canPunish()) {
			if ($data->player->hasPermission("ac.bypass")) {
				$this->violations = 0;
			} else {
				$this->punish($data);
			}
		}
	}

	protected function warn(PlayerData $data, array $extraData): void {
		$dataString = "";
		$n = count($extraData);
		$i = 1;
		foreach ($extraData as $name => $value) {
			$dataString .= "$name=$value";
			if ($i !== $n)
				$dataString .= " ";
			$i++;
		}
		$string = str_replace(["{prefix}", "{player}", "{check_name}", "{check_subtype}", "{violations}", "{data}"], [Esoteric::getInstance()->getSettings()->getPrefix(), $data->player->getName(), $this->name, $this->subType, var_export(round($this->violations, 2), true), $dataString], Esoteric::getInstance()->getSettings()->getAlertMessage());
		foreach (Esoteric::getInstance()->hasAlerts as $other) {
			if (microtime(true) - $other->lastAlertTime >= $other->alertCooldown) {
				$other->lastAlertTime = microtime(true);
				$other->player->sendMessage($string);
			}
		}
	}

	protected function canPunish(): bool {
		return $this->option("punishment_type") !== "none" && !$this->experimental;
	}

	protected function punish(PlayerData $data): void {
		$code = substr(sha1(rand()), 0, 7);
		if($this->option("punishment_type") === 'ban') {
			$string = Esoteric::getInstance()->getInstance()->getSettings()->getPrefix() . " Banned for " . $this->name . "({$this->subType}) [CODE: $code]";
			Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleDelayedTask(new BanTask($data->player, $string), 1);
		} else if($this->option("punishment_type") === 'kick') {
			$string = Esoteric::getInstance()->getInstance()->getSettings()->getPrefix() . " Kicked for " . $this->name . "({$this->subType}) [CODE: $code]";
			Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleDelayedTask(new KickTask($data->player, $string), 1);
		} else {
			$this->violations = 0;
		}
	}

	protected function setback(PlayerData $data): void {
		if (!$data->hasMovementSuppressed && $this->option("setback", false)) {
			$type = Esoteric::getInstance()->getSettings()->getSetbackType();
			switch ($type) {
				case Settings::SETBACK_SMOOTH:
					// this doesn't even work most of the time LOL
					/* $data->player->teleport($data->currentLocation, $data->currentYaw, $data->currentPitch);
					$motion = MathUtils::directionVectorFromValues(-($data->currentYaw), $data->onGround ? 0 : 90);
					$data->player->setMotion(PlayerData::$zeroVector);
					$data->player->setMotion($motion); */ break;
				case Settings::SETBACK_INSTANT:
					$position = $data->onGround ? $data->lastLocation : $data->lastOnGroundLocation;
					$data->player->teleport($position, $data->currentYaw, 0);
					break;
			}
			$data->hasMovementSuppressed = true;
		}
	}

	protected function reward(float $sub = 0.01): void {
		$this->violations = max($this->violations - $sub, 0);
	}

}
