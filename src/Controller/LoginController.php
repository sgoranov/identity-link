<?php
declare(strict_types=1);

namespace App\Controller;

use App\Form\Type\LoginType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'security_login', methods: ['GET', 'POST'])]
    public function login(Request $request, ParameterBagInterface $parameterBag): Response
    {
        $error = null;
        $session = $request->getSession();
        if ($session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR)) {
            $error = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
            $session->remove(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        }

        $authParams = $session->get('auth_request_params');
        $scopes = array_map('trim', explode(' ', $authParams['scope']));

        return $this->render('login/login.html.twig', [
            'form' => $this->createForm(LoginType::class),
            'error' => $error,
            'scopes' => $scopes,
            'client_name' => $authParams['client_id'],
            'resetPasswordUrl' => $parameterBag->get('reset_password_url'),
        ]);
    }

    #[Route('/login/2fa', name: 'security_2fa', methods: ['GET'])]
    public function twoFaAuth(Request $request): void
    {
        // This URL is used by AuthAuthenticator to check
        // and verify if the user has passed the 2FA.
    }
}
