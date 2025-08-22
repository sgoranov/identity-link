<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\AccessTokenRepository;
use App\Repository\RefreshTokenRepository;
use App\Security\EncryptionKeyLoader;
use App\Security\Jwt\JwtConfig;
use Defuse\Crypto\Crypto;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenValidator
{
    public function __construct(
        private readonly AccessTokenRepository $accessTokenRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly JwtConfig $jwtConfig,
        private readonly EncryptionKeyLoader $encryptionKeyLoader,
    ) {
    }

    public function validateToken(string $token, ?string $clientId = null): ?TokenValidationResult
    {
        if (JwtTokenGenerator::isJWT($token)) {
            return $this->validateAccessToken($token, $clientId);
        } else {
            return $this->validateRefreshToken($token, $clientId);
        }
    }

    public function validateAccessToken(string $token, ?string $clientId = null): ?TokenValidationResult
    {
        try {
            $key = new Key($this->jwtConfig->getPublicKey(), 'RS256');
            $decoded = (array) JWT::decode($token, $key);
        } catch (ExpiredException $e) {
            $decoded = (array) $e->getPayload();
        } catch (\Throwable $e) {
            return null;
        }

        $entity = $this->accessTokenRepository->getByIdentifier($decoded['jti']);
        if ($entity === null) {
            return null;
        }

        if ($clientId !== null && $entity->getClientIdentifier() !== $clientId) {
            return null;
        }

        return new TokenValidationResult($entity, $decoded, TokenType::ACCESS);
    }

    public function validateRefreshToken(string $token, ?string $clientId = null): ?TokenValidationResult
    {
        try {
            $decrypted = Crypto::decrypt($token, $this->encryptionKeyLoader->loadEncryptionKey());
            $decoded = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        $entity = $this->refreshTokenRepository->getByIdentifier($decoded['refresh_token_id']);
        if ($entity === null) {
            return null;
        }

        if ($clientId !== null && $decoded['client_id'] !== $clientId) {
            return null;
        }

        return new TokenValidationResult($entity, $decoded, TokenType::REFRESH);
    }
}