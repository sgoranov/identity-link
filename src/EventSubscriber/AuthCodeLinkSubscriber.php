<?php
declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\AccessTokenRepository;
use App\Security\EncryptionKeyLoader;
use Defuse\Crypto\Crypto;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records which authorization code each access token descends from, so that reusing the code can revoke them
 * (RFC 6749 §4.1.2, see AuthCodeRepository::isAuthCodeRevoked()).
 *
 * League does not pass the code to the access token repository, so the link is added after League has stored the
 * token. A token from the authorization code grant gets the code's identifier. A token from the refresh token grant
 * inherits it from the access token it replaces, so tokens obtained by refreshing stay linked to the code.
 */
final class AuthCodeLinkSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AccessTokenRepository $accessTokenRepository,
        private readonly EncryptionKeyLoader $encryptionKeyLoader,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestAccessTokenEvent::class => 'onAccessTokenIssued',
        ];
    }

    public function onAccessTokenIssued(RequestAccessTokenEvent $event): void
    {
        $parameters = (array) $event->getRequest()->getParsedBody();

        // League has validated and decrypted the code or refresh token before issuing the access token.
        $authCodeIdentifier = match ($parameters['grant_type'] ?? null) {
            'authorization_code' => $this->decrypt($parameters['code'])['auth_code_id'],
            'refresh_token' => $this->accessTokenRepository
                ->getByIdentifier($this->decrypt($parameters['refresh_token'])['access_token_id'])
                ?->getAuthCodeIdentifier(),
            default => null,
        };

        if ($authCodeIdentifier === null) {
            return;
        }

        $accessToken = $this->accessTokenRepository->getByIdentifier($event->getAccessToken()->getIdentifier());
        $accessToken->setAuthCodeIdentifier($authCodeIdentifier);
        $this->entityManager->flush();
    }

    /** @return array<string, mixed> */
    private function decrypt(string $payload): array
    {
        return json_decode(
            Crypto::decrypt($payload, $this->encryptionKeyLoader->loadEncryptionKey()),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
