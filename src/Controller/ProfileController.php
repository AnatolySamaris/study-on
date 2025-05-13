<?php

namespace App\Controller;

use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProfileController extends AbstractController
{
    public function __construct(
        private BillingClient $billingClient
    ) {
    }

    #[Route('/profile', name: 'app_profile')]
    #[IsGranted("ROLE_USER")]
    public function index(): Response
    {
        $user = $this->getUser();

        $billingUser = $this->billingClient
            ->getCurrentUser(
                $user->getApiToken()
            );

        return $this->render('profile/index.html.twig', [
            'user_username' => $billingUser->getUserIdentifier(),
            'user_role' => $billingUser->getRoles()[0],
            'user_balance' => $billingUser->getBalance()
        ]);
    }
}
