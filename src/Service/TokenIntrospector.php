<?php
declare(strict_types=1);

namespace App\Service;

class TokenIntrospector
{
    public function __construct(
        private readonly TokenValidator $tokenValidator,
    )
    {
    }

    public function introspect(string $token, string $clientId): array
    {
        $result = $this->tokenValidator->validateToken($token, $clientId);
        if ($result === null) {
            return ['active' => false];
        }

        if ($result->getType() === TokenType::ACCESS) {
            return $this->introspectAccessToken($result);
        } else {
            return $this->introspectRefreshToken($result);
        }
    }

    private function introspectAccessToken(TokenValidationResult $result): array
    {
        $accessToken = $result->getEntity();
        $decoded = $result->getDecoded();

        $isActive = $accessToken->getExpiryDateTime() > new \DateTimeImmutable()
            && $decoded['nbf'] < (new \DateTimeImmutable())->getTimestamp()
            && $accessToken->isRevoked() === false;

        if (!$isActive) {
            return [
                'active' => false,
            ];
        }

        return [
            'active' => true,
            'scope' => implode(' ', json_decode($accessToken->getScopes())),
            'client_id' => $accessToken->getClientIdentifier(),
            'token_type' => 'access_token',
            'exp' => $accessToken->getExpiryDateTime()->getTimestamp(),
            'iat' => $decoded['iat'] ?? null,
            'nbf' => $decoded['nbf'] ?? null,
            'sub' => $accessToken->getUserIdentifier(),
            'aud' => $decoded['aud'] ?? null,
            'jti' => $decoded['jti'] ?? null,
        ];
    }

    private function introspectRefreshToken(TokenValidationResult $result): array
    {
        $refreshToken = $result->getEntity();
        $decoded = $result->getDecoded();

        $isActive = $refreshToken->getExpiryDateTime() > new \DateTimeImmutable()
            && $refreshToken->isRevoked() === false;

        if (!$isActive) {
            return [
                'active' => false,
            ];
        }

        return [
            'active' => true,
            'scope' => implode(' ', $decoded['scopes'] ?? []),
            'client_id' => $decoded['client_id'],
            'token_type' => 'refresh_token',
            'exp' => $refreshToken->getExpiryDateTime()->getTimestamp(),
            'sub' => $decoded['user_id'],
            'jti' => $decoded['refresh_token_id'],
        ];
    }
}