<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ProfileController extends AbstractController
{
    public function __construct(
        private BillingClient $billingClient,
        private CourseRepository $courseRepository
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

    #[Route('/transactions', name: 'app_transactions_list')]
    #[IsGranted("ROLE_USER")]
    public function transactionsList(): Response
    {
        $user = $this->getUser();

        try {
            $transactions = $this->billingClient->getUserTransactions($user->getApiToken());

            // Добавляем id и название курса к транзакциям
            for ($i = 0; $i < count($transactions); $i++) {
                if ($transactions[$i]['type'] == "payment") {
                    $paidCourse = $this->courseRepository->findOneBy([
                        'code' => $transactions[$i]['course_code']
                    ]);
                    $transactions[$i]['course_id'] = $paidCourse->getId();
                    $transactions[$i]['course_title'] = $paidCourse->getTitle();
                }
            }
        } catch (BillingUnavailableException $e) {
            $transactions = [];
            $service_unavailable = true;
        }

        return $this->render('transaction/list.html.twig', [
            'transactions' => $transactions,
            'service_unavailable' => $service_unavailable ?? false
        ]);
    }
}
