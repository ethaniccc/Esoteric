<?php

namespace ethaniccc\Esoteric;

use ethaniccc\Esoteric\data\DataStorage;
use ethaniccc\Esoteric\listener\PMListener;
use ethaniccc\Esoteric\theme\Theme;
use pocketmine\event\HandlerList;
use pocketmine\plugin\PluginBase;

final class Esoteric extends PluginBase {

	/** @var Esoteric */
	private static $instance;

	/** @var Theme[] */
	public $themes = [];
	/** @var Theme */
	public $theme;
	/** @var DataStorage */
	public $dataStorage;
	/** @var PMListener */
	public $listener;

	public static function getInstance(): ?self {
		return self::$instance;
	}

	public function onEnable() {
		if (self::$instance !== null) {
			return;
		}
		self::$instance = $this;
		@mkdir($this->getDataFolder() . "themes");
		if (count(scandir($this->getDataFolder() . "themes")) === 0) {
			foreach (scandir($this->getFile() . "resources/themes") as $file) {
				if (strpos(strtolower($file), ".yml") !== false) {
					if (!$this->saveResource($file)) {
						$this->getLogger()->warning("Could not find resource $file");
					}
				}
			}
			// if there are still no themes after attempting to save the default themes, disable Esoteric.
			if (count(scandir($this->getDataFolder() . "themes")) - 2 === 0) {
				$this->getLogger()->notice("No themes were able to be loaded, Esoteric cannot function");
				$this->getServer()->getPluginManager()->disablePlugin($this);
				self::$instance = null;
				return;
			}
		}
		$themes = array_filter(scandir($this->getDataFolder() . "themes"), function (string $file): bool {
			return strpos(strtolower($file), ".yml") !== false && file_exists($this->getDataFolder() . "themes/$file");
		});
		foreach ($themes as $themeFile) {
			$theme = Theme::parse(yaml_parse(file_get_contents($this->getDataFolder() . "themes/$themeFile")));
			$themeFile = str_replace(".yml", "", $themeFile);
			if (isset($this->themes[$themeFile])) {
				$this->getLogger()->warning("Duplicate of $themeFile was found, not adding");
				continue;
			}
			$this->themes[$themeFile] = $theme;
		}
		$selected = $this->getConfig()->get("theme");
		$theme = $this->themes[$selected] ?? null;
		if ($theme === null) {
			$this->getLogger()->warning("Theme $selected was not found");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			self::$instance = null;
			return;
		}
		$this->theme = $theme;
		$this->dataStorage = new DataStorage();
		$this->listener = new PMListener();
		$this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
	}

	public function onDisable() {
		HandlerList::unregisterAll($this->listener);
	}

}