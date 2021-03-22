<?php

namespace ethaniccc\Esoteric;

use pocketmine\utils\TextFormat;

final class Settings{

    private $data;

    public function __construct(array $configData){
        $this->data = $configData;
    }

    public function getCheckSettings(string $type, string $subType) : ?array{
        if(!isset($this->data["detections"]))
            return null;
        if(!isset($this->data["detections"][$type]))
            return null;
        return ($this->data["detections"][$type])[$subType] ?? null;
    }

    public function getPrefix() : string{
        return ($this->data["prefix"] ?? "§l§7[§c!§7]") . TextFormat::RESET;
    }

    public function getWarnCooldown() : float{
        return $this->data["alert_cooldown"] ?? 5.0;
    }

    public function getWarnMessage() : string{
        return $this->data["alert_message"];
    }

}