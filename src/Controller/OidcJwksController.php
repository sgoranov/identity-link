<?php
declare(strict_types=1);

namespace App\Controller;

use Strobotti\JWK\KeyFactory;
use Strobotti\JWK\KeySet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OidcJwksController extends AbstractController
{

    #[Route('/jwks', name: 'oidc_jwks', methods: 'GET')]
    public function index(): Response
    {
        $jwtKey = $this->getParameter('jwt_key');
        $pem = file_get_contents($jwtKey['public']);
        $keyDetails = openssl_pkey_get_details(openssl_pkey_get_public($pem));

        $options = [
            'use' => 'sig',
            'kty' => 'RSA',
            'alg' => 'RS256',
            'kid' => $jwtKey['kid'],
            'n' => $this->base64urlEncode($keyDetails['rsa']['n']),
            'e' => $this->base64urlEncode($keyDetails['rsa']['e']),
        ];

        $keyFactory = new KeyFactory();
        $key = $keyFactory->createFromPem($pem, $options);

        $keySet = new KeySet();
        $keySet->addKey($key);

        $jsonResponse = new JsonResponse();
        $jsonResponse->setJson((string) $keySet);

        return $jsonResponse;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}