<?php

namespace ethaniccc\Esoteric;

use ethaniccc\Esoteric\command\EsotericCommand;
use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\PlayerDataManager;
use ethaniccc\Esoteric\data\sub\protocol\v428\PlayerAuthInputPacket;
use ethaniccc\Esoteric\listener\PMMPListener;
use ethaniccc\Esoteric\network\CustomNetworkInterface;
use ethaniccc\Esoteric\tasks\CreateBanwaveTask;
use ethaniccc\Esoteric\tasks\TickingTask;
use ethaniccc\Esoteric\thread\LoggerThread;
use ethaniccc\Esoteric\utils\banwave\Banwave;
use ethaniccc\Esoteric\utils\LevelUtils;
use ethaniccc\Esoteric\utils\MathUtils;
use ethaniccc\Esoteric\webhook\WebhookThread;
use pocketmine\block\BlockBreakInfo;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\raklib\RakLibInterface;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\timings\TimingsHandler;
use function array_filter;
use function array_keys;
use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function max;
use function mkdir;
use function scandir;
use function strtolower;
use const PHP_EOL;

final class Esoteric extends PluginBase {

	private static Esoteric $instance;
	public Settings $settings;
	public PlayerDataManager $dataManager;
	public TimingsHandler $checkTimings;

	/** @var PlayerData[] */
	public array $hasAlerts = [];
	/** @var string[] */
	public array $logCache = [];

	public array $exemptList = [];
	public ?Banwave $banwave = null;
	public CustomNetworkInterface $networkInterface;
	public LoggerThread $loggerThread;

	public function onEnable() : void {
		self::$instance = $this;
		MathUtils::$ZERO_VECTOR = new Vector3(0, 0, 0);
		LevelUtils::$ZERO_BREAK_INFO = new BlockBreakInfo(0);
		$this->settings = new Settings($this->getConfig()->getAll());
		$this->dataManager = new PlayerDataManager();

		$this->checkTimings = new TimingsHandler("Esoteric Checks");
		foreach (Server::getInstance()->getNetwork()->getInterfaces() as $interface) {
			if ($interface instanceof RakLibInterface) {
				Server::getInstance()->getNetwork()->unregisterInterface($interface);
				$interface->shutdown();
				$this->networkInterface = new CustomNetworkInterface(Server::getInstance());
				Server::getInstance()->getNetwork()->registerInterface($this->networkInterface);
				break;
			}
		}
		$this->loggerThread = new LoggerThread($this->getDataFolder() . 'esoteric.log');
		$this->loggerThread->start();
		Server::getInstance()->getPluginManager()->registerEvents(new PMMPListener(), $this);

		$webhookSettings = $this->getSettings()->getWebhookSettings();
		if($webhookSettings['alerts'] && $webhookSettings['link'] !== 'none'){
			WebhookThread::init();
		}

		PacketPool::getInstance()->registerPacket(new PlayerAuthInputPacket());
		$this->getScheduler()->scheduleRepeatingTask(new TickingTask(), 1);

		if (!file_exists($this->getDataFolder() . 'exempt.txt')) {
			file_put_contents($this->getDataFolder() . 'exempt.txt', '');
		}
		$contents = file_get_contents($this->getDataFolder() . 'exempt.txt');
		$this->exemptList = explode(PHP_EOL, $contents);

		Server::getInstance()->getCommandMap()->register($this->getName(), new EsotericCommand());
		if ($this->settings->getWaveSettings()['enabled']) {
			mkdir($this->getDataFolder() . 'banwaves');
			$count = count(scandir($this->getDataFolder() . 'banwaves')) - 2;
			if ($count === 0) {
				Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask($this->getDataFolder() . "banwaves/banwave-1.json", function (Banwave $banwave): void {
					$this->banwave = $banwave;
				}));
			} else {
				$filtered = array_filter(scandir($this->getDataFolder() . 'banwaves'), static function (string $file): bool {
					return strtolower(($array = explode(".", $file))[count($array) - 1]) === 'json';
				});
				Server::getInstance()->getAsyncPool()->submitTask(new CreateBanwaveTask($this->getDataFolder() . 'banwaves/' . $filtered[max(array_keys($filtered))], function (Banwave $banwave): void {
					$this->banwave = $banwave;
				}));
			}
		}
	}

	public function onDisable() : void {
		if ($this->getBanwave() !== null) $this->getBanwave()->update();
		file_put_contents($this->getDataFolder() . 'exempt.txt', implode(PHP_EOL, $this->exemptList));
		if (WebhookThread::valid()) WebhookThread::getInstance()->stop();
	}

	public static function getInstance(): self {
		return self::$instance;
	}

	public function getBanwave(): ?Banwave {
		return $this->banwave;
	}

	public function getSettings(): Settings {
		return $this->settings;
	}

}