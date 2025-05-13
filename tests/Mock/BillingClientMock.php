<?php

namespace App\Tests\Mock;

use App\Exception\InvalidCredentialsException;
use App\Security\User;
use App\Service\BillingClient;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class BillingClientMock extends BillingClient
{
    private $user = [
        'email' => 'user@mail.ru',
        'password' => 'password',
        'roles' => ['ROLE_USER'],
        'balance' => 1259.99,
    ];
    private $admin = [
        'email' => 'admin@mail.ru',
        'password' => 'password',
        'roles' => ['ROLE_SUPER_ADMIN'],
        'balance' => 99999.99,
    ];

    private function generateToken(string $username, array $roles): string
    {
        $signing_key = "signingKey";
        $header = [
            "alg" => "HS512",
            "typ" => "JWT"
        ];
        $header = base64_encode(json_encode($header));

        $payload =  [
            'username' => $username,
            'roles' => json_encode($roles),
            'exp' => (new \DateTime())->setTime('1', '0', '0', '0')->getTimestamp(),
        ];

        $payload = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha512', "$header.$payload", $signing_key, true));
        return "$header.$payload.$signature";
    }

    public function auth(string $username, string $password): array
    {

        if ($username === $this->user['email'] || $username === $this->admin['email']) {
            if ($password === $this->user['password']) {
                $token = $this->generateToken($username, $this->user['roles']);
            } elseif ($password === $this->admin['password']) {
                $token = $this->generateToken($username, $this->admin['roles']);
            } else {
                throw new InvalidCredentialsException('Invalid password');
            }
        } else {
            throw new InvalidCredentialsException('User with given username not found');
        }
        return [
            'user' => $username,
            'token' => $token,
        ];
    }

    public function register(string $username, string $password): User
    {
        if ($username === $this->user['email'] || $username === $this->admin['email']) {
            throw new InvalidCredentialsException('User with this email already exists');
        }
        $token = $this->generateToken($username, ['ROLE_USER']);

        $user = new User();
        $user->setApiToken($token);

        return $user;
    }

    public function getCurrentUser(string $token): User
    {
        $jwtParts = explode('.', $token);
        $payload = json_decode(base64_decode($jwtParts[1]), JSON_OBJECT_AS_ARRAY);
        $user = new User();

        if ($payload['username'] === $this->user['email']) {
            $user->setEmail($this->user['email'])
                ->setBalance($this->user['balance'])
                ->setRoles(json_decode($payload['roles'], true))
                ->setApiToken($token);
        } elseif ($payload['username'] === $this->admin['email']) {
            $user->setEmail($this->admin['email'])
                ->setBalance($this->admin['balance'])
                ->setRoles(json_decode($payload['roles'], true))
                ->setApiToken($token);
        } else {
            throw new AuthenticationException('Invalid JWT token');
        }

        return $user;
    }
}
