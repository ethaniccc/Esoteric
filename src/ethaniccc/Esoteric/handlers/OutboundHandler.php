<?php

namespace ethaniccc\Esoteric\handlers;

use ethaniccc\Esoteric\data\Data;
use ethaniccc\Esoteric\Esoteric;
use ethaniccc\Esoteric\thread\EsotericThread;
use ethaniccc\Esoteric\utils\ChunkDeserializer;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use Threaded;
use function var_dump;

final class OutboundHandler extends Threaded {

	public function execute(ClientboundPacket $packet, Data $data): void {
	}

}