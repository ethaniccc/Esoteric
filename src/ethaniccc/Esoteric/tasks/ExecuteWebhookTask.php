<?php

namespace ethaniccc\Esoteric\tasks;

use pocketmine\Player;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Internet;

class ExecuteWebhookTask extends AsyncTask {

    /** @var Player */
    private $url;
    /** @var string */
    private $content;

    public function __construct(string $url, $content) {
        $this->url = $url;
        $this->content = $content;
    }

	public function onRun() {
        Internet::postURL($this->url, json_encode($this->content), 10, ['Content-Type: application/json']);
    }

    public function onCompletion(Server $server) {
        // TODO: Do something, idk @ethanicc
    }
}
