<?php

namespace ethaniccc\Esoteric\utils\banwave;

use DateTime;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\tasks\AsyncClosureTask;
use ethaniccc\Esoteric\tasks\BanTask;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use function array_keys;
use function array_shift;
use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_numeric;
use function json_decode;
use function json_encode;
use function max;
use function str_replace;

final class Banwave {

	private mixed $players;
	/** @var string[] */
	private mixed $bannedPlayers;
	private mixed $issued;
	private mixed $completed;
	private string $path;
	private int $id;

	public function __construct(string $jsonData, string $path, int $id) {
		$data = json_decode($jsonData, true);
		$this->players = $data['players'];
		$this->bannedPlayers = $data['banned_players'];
		$this->issued = $data['issued'];
		$this->completed = $data['completed'];
		$this->path = $path;
		$this->id = $id;
	}

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
		$c = explode("/", $path);
		$c = $c[max(array_keys($c))];
		return $get ? new Banwave(file_get_contents($path), $path, (int) explode("-", explode(".", $c)[0])[1]) : null;
	}

	public static function get(string $path): self {
		return new Banwave(file_get_contents($path), $path, (int) explode("-", explode(".", $path)[0])[1]);
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

	public function execute(): void {
		$toJson = $this->toJson();
		$path = $this->path;
		Server::getInstance()->getAsyncPool()->submitTask(new AsyncClosureTask(static function () use ($toJson, $path): void {
			file_put_contents($path, $toJson);
		}, function (): void {
			$runs = 0;
			$maxRuns = count($this->getAllPlayers());
			$settings = Esoteric::getInstance()->getSettings()->getWaveSettings();
			$data = $this->getAllPlayers();
			$usernames = array_keys($data);
			$task = new ClosureTask(function () use (&$task, &$runs, &$data, &$usernames, $settings, $maxRuns): void {
				if ($runs === 0) {
					Server::getInstance()->broadcastMessage($settings['start_message']);
				} elseif ($runs > $maxRuns) {
					Server::getInstance()->broadcastMessage($settings['end_message']);
					$newID = $this->id + 1;
					$this->update();
					Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask(Esoteric::getInstance()->getDataFolder() . "banwaves/banwave-$newID.json", static function (Banwave $banwave): void {
						Esoteric::getInstance()->banwave = $banwave;
					}));
					$task->getHandler()->cancel();
				} else {
					$d = array_shift($data);
					$p = array_shift($usernames);
					$expiration = is_numeric($settings['ban_length']) ? (new DateTime('now'))->modify('+' . $settings['ban_length'] . ' day') : null;
					$string = str_replace(['{prefix}', '{code}', '{expires}'], [Esoteric::getInstance()->getSettings()->getPrefix(), $d['code'], $expiration !== null ? $expiration->format('m/d/y H:i') : 'Never'], Esoteric::getInstance()->getSettings()->getBanMessage());
					if (($player = Server::getInstance()->getPlayerExact($p)) !== null) {
						Esoteric::getInstance()->getScheduler()->scheduleTask(new BanTask($player, $string));
					} else {
						Server::getInstance()->getNameBans()->addBan($p, $string);
					}
					$this->addBanned($p);
					Server::getInstance()->broadcastMessage(str_replace(['{player}', '{id}'], [$p, $this->getId()], $settings['ban_message']));
				}

				$runs++;
			});
			Esoteric::getInstance()->getScheduler()->scheduleRepeatingTask($task, 30);
		}));
	}

	public function toJson(): string {
		return json_encode(['issued' => $this->issued, 'completed' => $this->completed, 'players' => $this->players, 'banned_players' => $this->bannedPlayers]);
	}

	public function getAllPlayers(): array {
		return $this->players ?? [];
	}

	public function update(): void {
		if (file_exists($this->path)) {
			file_put_contents($this->path, $this->toJson());
		}
	}

	public function addBanned(string $player): void {
		$this->bannedPlayers[$player] = $player;
	}

	public function getId(): int {
		return $this->id;
	}

}