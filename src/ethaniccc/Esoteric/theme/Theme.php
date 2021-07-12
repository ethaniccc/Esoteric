<?php

namespace ethaniccc\Esoteric\theme;

final class Theme {

	/** @var string */
	public $prefix;
	/** @var string */
	public $alertMessage;
	/** @var string */
	public $kickMessage;
	/** @var string */
	public $banMessage;

	public static function parse(array $data): self {
		$theme = new Theme();
		$theme->prefix = $data["prefix"] ?? "THEME_ERROR";
		$theme->alertMessage = $data["alert_message"] ?? "THEME_ERROR";
		$theme->kickMessage = $data["kick_message"] ?? "THEME_ERROR";
		$theme->banMessage = $data["ban_message"] ?? "THEME_ERROR";
		return $theme;
	}

}