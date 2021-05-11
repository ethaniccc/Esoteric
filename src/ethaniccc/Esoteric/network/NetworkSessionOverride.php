<?php

namespace ethaniccc\Esoteric\network;

use ethaniccc\Esoteric\Esoteric;
use pocketmine\network\mcpe\cache\ChunkCache;
use pocketmine\network\mcpe\compression\CompressBatchPromise;
use pocketmine\network\mcpe\compression\Compressor;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\PacketBroadcaster;
use pocketmine\network\mcpe\PacketSender;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\network\mcpe\protocol\PacketPool;
use pocketmine\network\mcpe\protocol\serializer\PacketBatch;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\NetworkSessionManager;
use pocketmine\player\UsedChunkStatus;
use pocketmine\Server;
use pocketmine\utils\Utils;
use function microtime;
use function spl_object_id;
use function strlen;
use function var_dump;

class NetworkSessionOverride extends NetworkSession {

	public function __construct(Server $server, NetworkSessionManager $manager, PacketPool $packetPool, PacketSender $sender, PacketBroadcaster $broadcaster, Compressor $compressor, string $ip, int $port) {
		parent::__construct($server, $manager, $packetPool, $sender, $broadcaster, $compressor, $ip, $port);
	}

	public function startUsingChunk(int $chunkX, int $chunkZ, \Closure $onCompletion) : void{
		Utils::validateCallableSignature(function() : void{}, $onCompletion);

		$world = $this->getPlayer()->getLocation()->getWorld();
		ChunkCache::getInstance($world, $this->getCompressor())->request($chunkX, $chunkZ)->onResolve(

		//this callback may be called synchronously or asynchronously, depending on whether the promise is resolved yet
			function(CompressBatchPromise $promise) use ($world, $onCompletion, $chunkX, $chunkZ) : void{
				$pKKS = (new PacketBatch($this->getCompressor()->decompress($promise->getResult())) )->getPackets(PacketPool::getInstance(), 1);
				foreach ($pKKS as $pKK) {
					$pKKK = $pKK[0];
					if ($pKKK instanceof LevelChunkPacket) {
						$s = new PacketSerializer($pKK[1]);
						$pKKK->decode($s);
						Esoteric::getInstance()->thread->queueOutbound(["{$this->getIp()} {$this->getPort()}"], $pKKK);
					}
				}
				if(!$this->isConnected()){
					return;
				}
				$currentWorld = $this->getPlayer()->getLocation()->getWorld();
				if($world !== $currentWorld or ($status = $this->getPlayer()->getUsedChunkStatus($chunkX, $chunkZ)) === null){
					$this->getLogger()->debug("Tried to send no-longer-active chunk $chunkX $chunkZ in world " . $world->getFolderName());
					return;
				}
				if(!$status->equals(UsedChunkStatus::REQUESTED())){
					//TODO: make this an error
					//this could be triggered due to the shitty way that chunk resends are handled
					//right now - not because of the spammy re-requesting, but because the chunk status reverts
					//to NEEDED if they want to be resent.
					return;
				}
				$world->timings->syncChunkSend->startTiming();
				try{
					$this->queueCompressed($promise);
					$onCompletion();
				}finally{
					$world->timings->syncChunkSend->stopTiming();
				}
			}
		);
	}

}