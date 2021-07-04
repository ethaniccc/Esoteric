<?php

namespace ethaniccc\Esoteric\utils;

use JsonMapper;
use JsonMapper_Exception;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationData;
use pocketmine\network\mcpe\protocol\types\login\ClientData;
use pocketmine\network\mcpe\protocol\types\login\JwtChain;
use pocketmine\network\PacketHandlingException;
use function is_null;

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
		/** @var ?AuthenticationData $extraData */
		$extraData = null;
		foreach ($chain->chain as $jwt) {
			//validate every chain element
			try {
				[, $claims,] = JwtUtils::parse($jwt);
			} catch (JwtException $e) {
				throw PacketHandlingException::wrap($e);
			}
			if (isset($claims['extraData'])) {
				if ($extraData !== null) {
					throw new PacketHandlingException('Found \'extraData\' more than once in chainData');
				}

				if (!is_array($claims['extraData'])) {
					throw new PacketHandlingException('\'extraData\' key should be an array');
				}
				$mapper = new JsonMapper;
				$mapper->bEnforceMapType = false; //TODO: we don't really need this as an array, but right now we don't have enough models
				$mapper->bExceptionOnMissingData = true;
				$mapper->bExceptionOnUndefinedProperty = true;
				try {
					/** @var AuthenticationData $extraData */
					$extraData = $mapper->map($claims['extraData'], new AuthenticationData);
				} catch (JsonMapper_Exception $e) {
					throw PacketHandlingException::wrap($e);
				}
			}
		}
		if (is_null($extraData)) {
			throw new PacketHandlingException('\'extraData\' not found in chain data');
		}
		return $extraData;
	}

}