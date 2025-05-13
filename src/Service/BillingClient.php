<?php

namespace App\Service;

use App\Exception\BillingUnavailableException;
use App\Exception\InvalidCredentialsException;
use App\Security\User;
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
            throw new AuthenticationException($userData['error']);
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
}
