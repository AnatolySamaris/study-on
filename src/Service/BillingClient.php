<?php

namespace App\Service;

use App\Dto\CourseDto;
use App\Entity\Course;
use App\Exception\BillingNotFoundException;
use App\Exception\BillingUnavailableException;
use App\Exception\InvalidCredentialsException;
use App\Exception\NotEnoughBalanceException;
use App\Security\User;
use DateTime;
use Exception;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class BillingClient
{
    private $billingUrl;

    public function __construct()
    {
        $this->billingUrl = $_ENV['BILLING_URL'];
    }

    private function request(
        string $url,
        array $body = [],
        array $headers = [],
        string $method = 'GET',
    ): array {
        $curl = curl_init();
        $curlHeaders = [];
        foreach ($headers as $header => $value) {
            $curlHeaders[] = "$header: $value";
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $curlHeaders
        ));

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl)['http_code'];

        if ($statusCode >= 500 || curl_error($curl)) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }
        curl_close($curl);

        return [
            'data' => $response,
            'statusCode' => $statusCode,
        ];
    }

    public function auth(string $username, string $password): array
    {
        $response = $this->request(
            $this->billingUrl . 'auth',
            [
                'email' => $username,
                'password' => $password
            ],
            ['Content-Type' => 'application/json'],
            'POST'
        );

        $data = json_decode($response['data'], true);

        if ($response['statusCode'] == 400 || $response['statusCode'] == 401) {
            throw new InvalidCredentialsException($data['error']);
        }

        return $data;
    }

    public function register(string $username, string $password): User
    {
        $response = $this->request(
            $this->billingUrl . 'register',
            [
                'email' => $username,
                'password' => $password
            ],
            ['Content-Type' => 'application/json'],
            'POST'
        );

        $data = json_decode($response['data'], true);

        if ($response['statusCode'] == 400) {
            throw new InvalidCredentialsException($data['error']);
        }

        $user = new User();
        $user->setApiToken($data['token']);
        $user->setRefreshToken($data['refresh_token']);

        return $user;
    }

    public function getCurrentUser(string $token): User
    {
        $response = $this->request(
            $this->billingUrl . 'users/current',
            [],
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'GET'
        );

        $userData = json_decode($response['data'], true);

        if ($response['statusCode'] == 401 || $response['statusCode'] == 404) {
            throw new AuthenticationException($userData['message']);
        } elseif ($response['statusCode'] == 500) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }

        $user = new User();
        $user->setApiToken($token);
        $user->setEmail($userData['username']);
        $user->setRoles($userData['roles']);
        $user->setBalance($userData['balance']);

        return $user;
    }

    public function refreshToken(User $user): User
    {
        $response = $this->request(
            $this->billingUrl . 'token/refresh',
            [
                'refresh_token' => $user->getRefreshToken()
            ],
            [
                'Content-Type' => 'application/json',
            ],
            'POST'
        );

        $tokenData = json_decode(
            $response['data'],
            true
        );

        $user->setRefreshToken($tokenData['refresh_token']);
        $user->setApiToken($tokenData['token']);

        return $user;
    }

    public function coursesList(): array|null
    {
        $response = $this->request(
            $this->billingUrl . 'courses',
            [],
            [
                'Content-Type' => 'application/json',
            ],
            'GET'
        );

        $coursesData = json_decode($response['data'], true);

        if ($response['statusCode'] == 500) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }

        return $coursesData;
    }

    public function courseInfoByCode(string $courseCode): array
    {
        $response = $this->request(
            $this->billingUrl . 'courses/' . $courseCode,
            [],
            [
                'Content-Type' => 'application/json',
            ],
            'GET'
        );

        $courseData = json_decode($response['data'], true);

        if ($response['statusCode'] == 404) {
            throw new BillingNotFoundException($courseData['error']);
        } elseif ($response['statusCode'] == 500) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }

        return $courseData;
    }

    public function isCourseAvailable(string $token, string $courseCode): bool|string
    {
        if ($token == null) {
            return false;
        }

        $response = $this->request(
            $this->billingUrl . 'transactions?filter[course_code]=' . $courseCode,
            [],
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'GET'
        );

        if ($response['statusCode'] == 500) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }

        $transactionsData = json_decode($response['data'], true);

        if (count($transactionsData) > 0) {
            $lastTransaction = $this->getLatestTransaction($transactionsData);
            if (isset($lastTransaction['expires_at'])) {    // Проверка для арендуемых курсов
                $expiresAt = new DateTime($lastTransaction['expires_at']);
                $now = new DateTime();
                if ($expiresAt >= $now) {
                    return $expiresAt->format('d.m.Y');
                } else {
                    return false;
                }
            } else {    // Если покупаемый курс - достаточно знать что транзакция покупки была
                return true;
            }
        }

        return false;
    }

    public function payCourse(string $token, string $courseCode): bool
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        $response = $this->request(
            $this->billingUrl . 'courses/' . $courseCode . '/pay',
            [],
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'POST'
        );

        if ($response['statusCode'] == 406) {
            throw new NotEnoughBalanceException();
        } elseif ($response['statusCode'] == 500) {
            throw new BillingUnavailableException();
        }

        $paymentData = json_decode($response['data'], true);

        if ($paymentData['success'] == true) {
            return true;
        } else {
            throw new BillingUnavailableException();
        }
    }

    public function isEnoughBalance(string $token, string $courseCode): bool
    {
        if ($token == null) {
            return false;
        }

        $user = $this->getCurrentUser($token);
        $courseData = $this->courseInfoByCode($courseCode);

        if ($courseData['type'] == 'free' || $courseData['price'] <= $user->getBalance()) {
            return true;
        } else {
            return false;
        }
    }

    public function getUserTransactions(string $token): array
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        $response = $this->request(
            $this->billingUrl . 'transactions',
            [],
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'GET'
        );

        if ($response['statusCode'] == 500) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }

        $transactionsData = json_decode($response['data'], true);

        if (!$transactionsData) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }

        // Сортировка по убыванию даты
        usort($transactionsData, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return $transactionsData;
    }

    public function createCourse(string $token, CourseDto $courseDto)
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        $response = $this->request(
            $this->billingUrl . 'courses/new',
            $courseDto->asArray(),
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'POST'
        );

        if ($response['statusCode'] == 201) {
            return;
        } else {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }
    }

    public function editCourse(string $token, string $courseCode, CourseDto $courseDto)
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        $response = $this->request(
            $this->billingUrl . 'courses/' . $courseCode,
            $courseDto->asArray(),
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'POST'
        );
        
        if ($response['statusCode'] == 200) {
            return;
        } else {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }
    }

    public function deleteCourse(string $token, string $courseCode)
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        $response = $this->request(
            $this->billingUrl . 'courses/' . $courseCode . '/delete',
            [],
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'POST'
        );
        
        if ($response['statusCode'] == 200) {
            return;
        } else {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }
    }

    private function getLatestTransaction(array $transactions): array
    {
        if (empty($transactions)) {
            return [];
        }

        $latest = null;
        $latestDate = null;

        foreach ($transactions as $transaction) {
            $currentDate = new DateTime($transaction['created_at']);
            
            if ($latestDate === null || $currentDate > $latestDate) {
                $latest = $transaction;
                $latestDate = $currentDate;
            }
        }

        return $latest;
    }
}
