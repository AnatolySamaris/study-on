<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Form\RegistrationFormType;
use App\Security\StudyOnBillingAuthenticator;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render(
            'security/login.html.twig',
            [
                'last_username' => $lastUsername,
                'error' => $error
            ]
        );
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank');
    }

    #[Route(path: '/register', name: 'app_register')]
    public function register(
        Request $request,
        BillingClient $billingClient,
        UserAuthenticatorInterface $userAuthenticator,
        StudyOnBillingAuthenticator $billingAuthenticator,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $user = $this->getUser();

        if ($user) {
            return new RedirectResponse(
                $urlGenerator->generate('app_profile')
            );
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $username = $form->get('email')->getData();
            $password = $form->get('password')->getData();

            try {
                $user = $billingClient->register($username, $password);
                $billingUser = $billingClient->getCurrentUser($user->getApiToken());

                $user->setEmail($billingUser->getUserIdentifier());
                $user->setBalance($billingUser->getBalance());
                $user->setRoles($billingUser->getRoles());
            } catch (\Exception $e) {
                return $this->render('security/register.html.twig', [
                    'registerForm' => $form->createView(),
                    'error' => $e->getMessage(),
                    'form' => $form,
                ]);
            }

            return $userAuthenticator->authenticateUser(
                $user,
                $billingAuthenticator,
                $request
            );
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
            'error' => null
        ]);
    }
}
