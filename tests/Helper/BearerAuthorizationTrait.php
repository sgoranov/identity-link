<?php
declare(strict_types=1);

namespace App\Tests\Helper;

use App\Repository\AccessTokenRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait BearerAuthorizationTrait
{
    /** @return array{HTTP_AUTHORIZATION: string} */
    private function authorizationHeader(KernelBrowser $client, string $accessTokenIdentifier): array
    {
        $accessTokenRepository = $client->getContainer()->get(AccessTokenRepository::class);
        $testHelper = $client->getContainer()->get(TestHelper::class);
        $accessToken = $accessTokenRepository->findOneBy([
            'identifier' => $accessTokenIdentifier,
        ]);

        return [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $testHelper->generateJwtPayloadByAccessToken($accessToken),
        ];
    }
}
