<?php


namespace ethaniccc\Esoteric\check;

use DateTime;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\Settings;
use ethaniccc\Esoteric\tasks\BanTask;
use ethaniccc\Esoteric\tasks\KickTask;
use ethaniccc\Esoteric\utils\handler\DebugHandler;
use ethaniccc\Esoteric\webhook\Embed;
use ethaniccc\Esoteric\webhook\Message;
use ethaniccc\Esoteric\webhook\Webhook;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\CorrectPlayerMovePredictionPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\timings\TimingsHandler;
use function array_keys;
use function count;
use function is_numeric;
use function max;
use function microtime;
use function round;
use function str_replace;
use function var_export;

abstract class Check {

	public static $settings = [];
	public static $timings = [];
	/** @var int[] */
	private static $TOTAL_VIOLATIONS = [];
	public $name;
	public $subType;
	public $description;
	public $experimental;
	public $violations = 0;
	public $buffer = 0;
	public $fallbackSetback;

	public function __construct(string $name, string $subType, string $description, bool $experimental = false) {
		$this->name = $name;
		$this->subType = $subType;
		$this->description = $description;
		$this->experimental = $experimental;
		$this->fallbackSetback = false;
		if (!isset(self::$settings["$name:$subType"])) {
			$settings = Esoteric::getInstance()->getSettings()->getCheckSettings($name, $subType);
			if ($settings === null) {
				$settings = ["enabled" => true, "punishment_type" => "none", "max_vl" => 20];
			}
			self::$settings["$name:$subType"] = $settings;
		}
		if (!isset(self::$timings["$name:$subType"])) {
			self::$timings["$name:$subType"] = new TimingsHandler("Esoteric Check $name($subType)", Esoteric::getInstance()->listener->checkTimings);
		}
	}

	public function getData(): array {
		return ["violations" => $this->violations, "description" => $this->description, "full_name" => $this->name . " ({$this->subType})", "name" => $this->name, "subType" => $this->subType];
	}

	public function debug(PlayerData $data, string $msg): void {
		$handler = $this->getDebugHandler($data);
		if ($handler !== null) {
			$handler->broadcast($msg);
		}
	}

	public function getDebugHandler(PlayerData $data): ?DebugHandler {
		return $data->debugHandlers[$this->name . " ({$this->subType})"] ?? null;
	}

	public function getTimings(): TimingsHandler {
		return self::$timings["{$this->name}:{$this->subType}"];
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
		$dataString = self::getDataString($extraData);
		if (!$this->experimental) {
			++$this->violations;
			if (!isset(self::$TOTAL_VIOLATIONS[$data->player->getName()])) {
				self::$TOTAL_VIOLATIONS[$data->player->getName()] = 0;
			}
			self::$TOTAL_VIOLATIONS[$data->player->getName()] += 1;
			$banwaveSettings = Esoteric::getInstance()->getSettings()->getWaveSettings();
			if ($banwaveSettings["enabled"] && self::$TOTAL_VIOLATIONS[$data->player->getName()] >= $banwaveSettings["violations"] && !$data->player->hasPermission("ac.bypass")) {
				$wave = Esoteric::getInstance()->getBanwave();
				$wave->add($data->player->getName(), $this->getCodeName());
			}
			$this->sendAlertWebhook($data->player->getName(), $dataString);
		}
		if ($data->player->isOnline()) {
			$this->warn($data, $extraData);
			if ($this->violations >= $this->option("max_vl") && $this->canPunish()) {
				$data->player->hasPermission("ac.bypass") ? $this->violations = 0 : $this->punish($data);
			}
		}
	}

	public static function getDataString(array $data): string {
		$dataString = "";
		$n = count($data);
		$i = 1;
		foreach ($data as $name => $value) {
			$dataString .= "$name=$value";
			if ($i !== $n) {
				$dataString .= " ";
			}
			$i++;
		}
		return $dataString;
	}

	public function getCodeName(): string {
		return $this->option("code", "{$this->name}({$this->subType})");
	}

	private function sendAlertWebhook(string $player, string $debug): void {
		$webhookSettings = Esoteric::getInstance()->getSettings()->getWebhookSettings();
		$webhookLink = $webhookSettings["link"];
		$canSend = $webhookSettings["alerts"] && $webhookLink !== "none";

		if (!$canSend) {
			return;
		}

		$message = new Message();
		$message->setContent("");

		$embed = new Embed();
		$embed->setTitle("Anti-cheat alert");
		$embed->setColor(0xFFC300);
		$embed->setFooter((new DateTime('now'))->format("m/d/y @ h:m:s A"));
		$embed->setDescription("
		Player: **`$player`**
		Violations: **`{$this->violations}`**
		Codename: **`{$this->getCodeName()}`**
		Detection name: **`{$this->name} ({$this->subType})`**
		Debug data: **`$debug`**
		");

		$message->addEmbed($embed);

		$webhook = new Webhook($webhookLink, $message);
		$webhook->send();
	}

	protected function warn(PlayerData $data, array $extraData): void {
		$dataString = "";
		$n = count($extraData);
		$i = 1;
		foreach ($extraData as $name => $value) {
			$dataString .= "$name=$value";
			if ($i !== $n) {
				$dataString .= " ";
			}
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
		$esoteric = Esoteric::getInstance();
		if ($this->option("punishment_type") === "ban") {
			$data->isDataClosed = true;
			$l = $esoteric->getSettings()->getBanLength();
			$expiration = is_numeric($l) ? (new DateTime('now'))->modify("+" . (int)$l . " day") : null;
			$esoteric->getPlugin()->getScheduler()->scheduleTask(new BanTask($data->player, $this->getCodeName(), $expiration));
			$this->sendPunishmentWebhook($data->player->getName(), "ban");
			if (($bc = $esoteric->getSettings()->getBanBroadcast()) !== "none") {
				$esoteric->getServer()->broadcastMessage(str_replace(["{prefix}", "{player}", "{check_name}", "{code_name}", "{violations}", "{expires}"], [$esoteric->getSettings()->getPrefix(), $data->player->getName(), $this->name, $this->getCodeName(), $this->violations, $expiration !== null ? $expiration->format("m/d/y H:i") : "Never"], $bc));
			}
		} elseif ($this->option("punishment_type") === "kick") {
			$data->isDataClosed = true;
			$string = str_replace(["{prefix}", "{code}"], [$esoteric->getSettings()->getPrefix(), $this->getCodeName()], $esoteric->getSettings()->getKickMessage());
			$esoteric->getPlugin()->getScheduler()->scheduleTask(new KickTask($data->player, $string));
			$this->sendPunishmentWebhook($data->player->getName(), "kick");
			if (($bc = $esoteric->getSettings()->getKickBroadcast()) !== "none") {
				$esoteric->getServer()->broadcastMessage(str_replace(["{prefix}", "{player}", "{check_name}", "{code_name}", "{violations}"], [$esoteric->getSettings()->getPrefix(), $data->player->getName(), $this->name, $this->getCodeName(), $this->violations], $bc));
			}
		} else {
			$this->violations = 0;
		}
	}

	private function sendPunishmentWebhook(string $player, string $type): void {
		$webhookSettings = Esoteric::getInstance()->getSettings()->getWebhookSettings();
		$webhookLink = $webhookSettings["link"];
		$canSend = $webhookSettings["alerts"] && $webhookLink !== "none";

		if (!$canSend) {
			return;
		}

		$message = new Message();
		$message->setContent("");

		$embed = new Embed();
		$embed->setTitle("Anti-cheat punishment");
		$embed->setColor(0xFF0000);
		$embed->setFooter((new DateTime('now'))->format("m/d/y @ h:m:s A"));
		$embed->setDescription("
		Player: **`$player`**
		Type: **`$type`**
		Codename: **`{$this->getCodeName()}`**
		Detection name: **`{$this->name} ({$this->subType})`**
		");
		$message->addEmbed($embed);

		$webhook = new Webhook($webhookLink, $message);
		$webhook->send();
	}

	protected function setback(PlayerData $data): void {
		if (!$data->hasMovementSuppressed && $this->option("setback", $this->fallbackSetback)) {
			$type = Esoteric::getInstance()->getSettings()->getSetbackType();
			switch ($type) {
				case Settings::SETBACK_SMOOTH:
					$delta = ($data->packetDeltas[0] ?? new Vector3(0, -0.08 * 0.98, 0));
					$packet = CorrectPlayerMovePredictionPacket::create(($data->onGround ? $data->lastLocation : $data->lastOnGroundLocation)->add(0, 1.62, 0), $delta, $data->onGround, array_keys($data->packetDeltas)[0] ?? 0);
					$data->player->dataPacket($packet);
					break;
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