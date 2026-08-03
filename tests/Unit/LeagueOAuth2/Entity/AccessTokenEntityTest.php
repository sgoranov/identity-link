<?php
declare(strict_types=1);

namespace App\Tests\Unit\LeagueOAuth2\Entity;

use App\LeagueOAuth2\CryptKey;
use App\LeagueOAuth2\Entity\AccessTokenEntity;
use App\LeagueOAuth2\Entity\ClientEntity;
use PHPUnit\Framework\TestCase;

final class AccessTokenEntityTest extends TestCase
{
    public function testUsesClientAudienceAndKeepsClientIdentifierAsASeparateClaim(): void
    {
        $client = new ClientEntity();
        $client->setIdentifier('administration-ui');
        $client->setAudience('https://example.com/identity-link');

        $token = new AccessTokenEntity();
        $token->setClient($client);
        $token->setIdentifier('token-id');
        $token->setUserIdentifier('user-id');
        $token->setScopes([]);
        $token->setExpiryDateTime(new \DateTimeImmutable('+5 minutes'));
        $token->setPrivateKey(new CryptKey(
            'file://' . dirname(__DIR__, 3) . '/resources/private.key',
            null,
            false,
            'test-key',
        ));

        $payload = $this->decodePayload($token->toString());

        $this->assertSame('https://example.com/identity-link', $payload['aud']);
        $this->assertSame('administration-ui', $payload['client_id']);
    }

    private function decodePayload(string $jwt): array
    {
        $encodedPayload = explode('.', $jwt)[1];
        $decodedPayload = base64_decode(strtr($encodedPayload, '-_', '+/'), true);

        self::assertNotFalse($decodedPayload);

        return json_decode($decodedPayload, true, flags: JSON_THROW_ON_ERROR);
    }
}
