<?php
declare(strict_types=1);

namespace App\Controller;

use Strobotti\JWK\KeyFactory;
use Strobotti\JWK\KeySet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JwksController extends AbstractController
{

    #[Route('/jwks', name: 'openid_jwks', methods: 'GET')]
    public function index(): Response
    {
        $jwtKey = $this->getParameter('jwt_key');

        $pem = file_get_contents($jwtKey['public']);

        $options = [
            'use' => 'sig',
            'kty' => 'RSA',
            'alg' => 'RS256',
            'kid' => $jwtKey['kid'],
        ];

        $keyFactory = new KeyFactory();
        $key = $keyFactory->createFromPem($pem, $options);

        $keySet = new KeySet();
        $keySet->addKey($key);

        $jsonResponse = new JsonResponse();
        $jsonResponse->setJson((string) $keySet);

        return $jsonResponse;
    }
}