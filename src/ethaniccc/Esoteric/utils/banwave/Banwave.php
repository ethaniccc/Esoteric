<?php

namespace ethaniccc\Esoteric\utils\banwave;

use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\AsyncClosureTask;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

final class Banwave {

	public static function create(string $path, bool $new, bool $get = false): ?self {
		if ($new) {
			file_put_contents($path, '
			{
				"issued": false,
				"completed": false,
				"players": {
				
				},
				"banned_players": {
				
				}
			}
			');
		}
		return $get ? new Banwave(file_get_contents($path), $path, (int) explode("-", explode(".", $path)[0])[1]) : null;
	}

	public static function get(string $path): self {
		return new Banwave(file_get_contents($path), $path, (int) explode("-", explode(".", $path)[0])[1]);
	}

	/** @var array */
	private $players = [];
	/** @var string[] */
	private $bannedPlayers = [];
	/** @var bool */
	private $issued = false;
	/** @var bool */
	private $completed = false;
	/** @var string */
	private $path = "";
	/** @var int */
	private $id;

	public function __construct(string $jsonData, string $path, int $id) {
		$data = json_decode($jsonData, true);
		$this->players = $data["players"];
		$this->bannedPlayers = $data["banned_players"];
		$this->issued = $data["issued"];
		$this->completed = $data["completed"];
		$this->path = $path;
		$this->id = $id;
	}

	public function toJson(): string {
		return json_encode(["issued" => $this->issued, "completed" => $this->completed, "players" => $this->players, "banned_players" => $this->bannedPlayers]);
	}

	public function update(): void {
		if (file_exists($this->path)) {
			file_put_contents($this->path, $this->toJson());
		}
	}

	public function getPath(): string {
		return $this->path;
	}

	public function isCompleted(): bool {
		return $this->completed;
	}

	public function isIssued(): bool {
		return $this->issued;
	}

	public function getAllPlayers(): array {
		return $this->players ?? [];
	}

	public function removeFromList(string $player): void {
		unset($this->players[$player]);
	}

	public function add(string $player, string $codename): void {
		if (!isset($this->players[$player])) {
			$this->players[$player] = ["code" => $codename];
		}
	}

	public function getBannedPlayers(): array {
		return $this->bannedPlayers;
	}

	public function removeFromBanned(string $player): void {
		unset($this->bannedPlayers[$player]);
	}

	public function addBanned(string $player): void {
		$this->bannedPlayers[$player] = $player;
	}

	public function getId(): int {
		return $this->id;
	}

	public function execute(): void {
		$toJson = $this->toJson();
		$path = $this->path;
		Server::getInstance()->getAsyncPool()->submitTask(new AsyncClosureTask(function () use ($toJson, $path): void {
			file_put_contents($path, $toJson);
		}, function (): void {
			$runs = 0;
			$maxRuns = count($this->getAllPlayers());
			$settings = Esoteric::getInstance()->getSettings()->getWaveSettings();
			$data = $this->getAllPlayers();
			$usernames = array_keys($data);
			$task = new ClosureTask(function (int $currentTick) use (&$task, &$runs, &$data, &$usernames, $settings, $maxRuns): void {
				if ($runs === 0) {
					Server::getInstance()->broadcastMessage($settings["start_message"]);
				} elseif ($runs > $maxRuns) {
					Server::getInstance()->broadcastMessage($settings["end_message"]);
					$newID = $this->id + 1;
					$this->update();
					Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask(Esoteric::getInstance()->getPlugin()->getDataFolder() . "banwaves/banwave-$newID.json", function (Banwave $banwave): void {
						Esoteric::getInstance()->banwave = $banwave;
					}));
					$task->getHandler()->cancel();
				} else {
					$d = array_shift($data);
					$p = array_shift($usernames);
					Server::getInstance()->getNameBans()->addBan($p, "Ban wave {$this->id} (" . $d["code"] . ")", null, "Esoteric");
					$this->addBanned($p);
					if (($player = Server::getInstance()->getPlayerExact($p)) !== null) {
						$player->kick(str_replace(["{prefix}", "{code}"], [Esoteric::getInstance()->getSettings()->getPrefix(), $d["code"]], Esoteric::getInstance()->getSettings()->getBanMessage()));
					}
					Server::getInstance()->broadcastMessage(str_replace(["{player}", "{id}"], [$p, $this->getId()], $settings["ban_message"]));
				}

				$runs++;
			});
			Esoteric::getInstance()->getPlugin()->getScheduler()->scheduleRepeatingTask($task, 30);
		}));
	}

}