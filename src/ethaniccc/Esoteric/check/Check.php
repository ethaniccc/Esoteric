<?php


namespace ethaniccc\Esoteric\check;

use CortexPE\DiscordWebhookAPI\Embed;
use CortexPE\DiscordWebhookAPI\Message;
use CortexPE\DiscordWebhookAPI\Webhook;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\Settings;
use ethaniccc\Esoteric\tasks\BanTask;
use ethaniccc\Esoteric\tasks\KickTask;
use pocketmine\network\mcpe\protocol\DataPacket;

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
		$extraData["ping"] = $data->player->getPing();
		if (!$this->experimental) {
			++$this->violations;
			$webhookSettings = Esoteric::getInstance()->getSettings()->getWebhookSettings();
			$webhookLink = $webhookSettings["link"];
			$canSend = $webhookSettings["alerts"] && $webhookLink !== "none";
			if ($canSend) {
				$message = new Message();
				$message->setContent("");

				$dataString = "";
				$n = count($extraData);
				$i = 1;
				foreach ($extraData as $name => $value) {
					$dataString .= "$name=$value";
					if ($i !== $n)
						$dataString .= " ";
					$i++;
				}

				$embed = new Embed();
				$embed->setTitle("Anti-cheat alert");
				$embed->setColor(0xFFC300);
				$embed->setDescription("
				Player: **`{$data->player->getName()}`**
				Violations: **`{$this->violations}`**
				Codename: **`{$this->getCodeName()}`**
				Detection name: **`{$this->name} ({$this->subType})`**
				Debug data: **`$dataString`**
				");
				$message->addEmbed($embed);

				$webhook = new Webhook($webhookLink, $message);
				$webhook->send();
			}
		}
		$this->warn($data, $extraData);
		if ($this->violations >= $this->option("max_vl") && $this->canPunish()) {
			if ($data->player->hasPermission("ac.bypass")) {
				$this->violations = 0;
			} else {
				$this->punish($data);
			}
		}
	}

	public function getCodeName(): string {
		return $this->option("code", "{$this->name}({$this->subType})");
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
		Esoteric::getInstance()->getPlugin()->getLogger()->debug($string);
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
		$webhookSettings = Esoteric::getInstance()->getSettings()->getWebhookSettings();
		$webhookLink = $webhookSettings["link"];
		$canSend = $webhookSettings["punishments"] && $webhookLink !== "none";
		if ($this->option("punishment_type") === 'ban') {
			$data->isDataClosed = true;
			$string = str_replace(["{prefix}", "{code}"], [Esoteric::getInstance()->getSettings()->getPrefix(), $this->getCodeName()], Esoteric::getInstance()->getSettings()->getBanMessage());
			Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleDelayedTask(new BanTask($data->player, $string), 1);
			if ($canSend) {
				$message = new Message();
				$message->setContent("");

				$embed = new Embed();
				$embed->setTitle("Anti-cheat punishment");
				$embed->setColor(0xFF0000);
				$embed->setDescription("
				Player: **`{$data->player->getName()}`**
				Type: **`ban`**
				Codename: **`{$this->getCodeName()}`**
				Detection name: **`{$this->name} ({$this->subType})`**
				");
				$message->addEmbed($embed);

				$webhook = new Webhook($webhookLink, $message);
				$webhook->send();
			}
		} elseif ($this->option("punishment_type") === "kick") {
			$data->isDataClosed = true;
			$string = str_replace(["{prefix}", "{code}"], [Esoteric::getInstance()->getSettings()->getPrefix(), $this->getCodeName()], Esoteric::getInstance()->getSettings()->getKickMessage());
			Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleDelayedTask(new KickTask($data->player, $string), 1);
			if ($canSend) {
				$message = new Message();
				$message->setContent("");

				$embed = new Embed();
				$embed->setTitle("Anti-cheat punishment");
				$embed->setColor(0xFF0000);
				$embed->setDescription("
				Player: **`{$data->player->getName()}`**
				Type: **`kick`**
				Codename: **`{$this->getCodeName()}`**
				Detection name: **`{$this->name} ({$this->subType})`**
				");
				$message->addEmbed($embed);

				$webhook = new Webhook($webhookLink, $message);
				$webhook->send();
			}
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