<?php

namespace ethaniccc\Esoteric\utils;

use ethaniccc\Esoteric\data\PlayerData;
use ethaniccc\Esoteric\data\process\ACKHandler;
use ethaniccc\Esoteric\Esoteric;
use JsonMapper;
use JsonMapper_Exception;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationData;
use pocketmine\network\mcpe\protocol\types\login\ClientData;
use pocketmine\network\mcpe\protocol\types\login\JwtChain;
use pocketmine\network\PacketHandlingException;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;
use raklib\RakLib;

class PacketUtils {

	public static function parseClientData(string $clientDataJwt): ClientData {
		try {
			[, $clientDataClaims,] = JwtUtils::parse($clientDataJwt);
		} catch (JwtException $e) {
			throw PacketHandlingException::wrap($e);
		}

		$mapper = new JsonMapper;
		$mapper->bEnforceMapType = false; //TODO: we don't really need this as an array, but right now we don't have enough models
		$mapper->bExceptionOnMissingData = true;
		$mapper->bExceptionOnUndefinedProperty = true;
		try {
			$clientData = $mapper->map($clientDataClaims, new ClientData);
		} catch (JsonMapper_Exception $e) {
			throw PacketHandlingException::wrap($e);
		}
		return $clientData;
	}

	public static function fetchAuthData(JwtChain $chain): AuthenticationData {
		/** @var AuthenticationData|null $extraData */
		$extraData = null;
		foreach ($chain->chain as $jwt) {
			//validate every chain element
			try {
				[, $claims,] = JwtUtils::parse($jwt);
			} catch (JwtException $e) {
				throw PacketHandlingException::wrap($e);
			}
			if (isset($claims["extraData"])) {
				if ($extraData !== null) {
					throw new PacketHandlingException("Found 'extraData' more than once in chainData");
				}

				if (!is_array($claims["extraData"])) {
					throw new PacketHandlingException("'extraData' key should be an array");
				}
				$mapper = new JsonMapper;
				$mapper->bEnforceMapType = false; //TODO: we don't really need this as an array, but right now we don't have enough models
				$mapper->bExceptionOnMissingData = true;
				$mapper->bExceptionOnUndefinedProperty = true;
				try {
					/** @var AuthenticationData $extraData */
					$extraData = $mapper->map($claims["extraData"], new AuthenticationData);
				} catch (JsonMapper_Exception $e) {
					throw PacketHandlingException::wrap($e);
				}
			}
		}
		if ($extraData === null) {
			throw new PacketHandlingException("'extraData' not found in chain data");
		}
		return $extraData;
	}

	/**
	 * @param PlayerData $data
	 * @param BatchPacket $packet
	 * @param bool $needACK
	 * @param callable|null $ackResponse
	 * This function exists so that I don't have to deal with un-needed decoding of packets that Esoteric sends. This sends a batch packet to the player
	 * without calling the DataPacketReceiveEvent.
	 */
	public static function sendPacketSilent(PlayerData $data, BatchPacket $packet, bool $needACK = false, callable $ackResponse = null): void {
		if ($needACK) {
			$pk = new EncapsulatedPacket();
			$handler = ACKHandler::getInstance();
			$id = $handler->next($data->networkIdentifier);
			$pk->identifierACK = $id;
			$pk->buffer = $packet->buffer;
			$pk->reliability = PacketReliability::RELIABLE_ORDERED;
			$pk->orderChannel = 0;
			if ($ackResponse !== null) {
				$handler->add($data->networkIdentifier, $id, $ackResponse);
			}
		} else {
			if (!isset($packet->__encapsulatedPacket)) {
				$packet->__encapsulatedPacket = new CachedEncapsulatedPacket;
				$packet->__encapsulatedPacket->identifierACK = null;
				$packet->__encapsulatedPacket->buffer = $packet->buffer;
				$packet->__encapsulatedPacket->reliability = PacketReliability::RELIABLE_ORDERED;
				$packet->__encapsulatedPacket->orderChannel = 0;
			}
			$pk = $packet->__encapsulatedPacket;
		}
		Esoteric::getInstance()->networkInterface->getServerHandler()->sendEncapsulated($data->networkIdentifier, $pk, ($needACK ? RakLib::FLAG_NEED_ACK : 0) | RakLib::PRIORITY_NORMAL);
	}

}