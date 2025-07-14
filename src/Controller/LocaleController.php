<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/switch-locale/{locale}', name: 'switch_locale')]
    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        $request->getSession()->set('_locale', $locale);

        // Redirect back to the previous page
        $referer = $request->headers->get('referer') ?? $this->generateUrl('security_login');
        return new RedirectResponse($referer);
    }
}