<?php

namespace App\Tests\Mock;

use App\Dto\CourseDto;
use App\Enum\CourseType;
use App\Exception\BillingUnavailableException;
use App\Exception\InvalidCredentialsException;
use App\Exception\NotEnoughBalanceException;
use App\Security\User;
use App\Service\BillingClient;
use DateTime;
use Exception;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class BillingClientMock extends BillingClient
{
    private $users = [
        'user@mail.ru' => [
            'email' => 'user@mail.ru',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'balance' => 1259.99,
        ],
        'admin@mail.ru' => [
            'email' => 'admin@mail.ru',
            'password' => 'password',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'balance' => 99999.99,
        ],
        'new_user@mail.ru' => [
            'email' => 'new_user@mail.ru',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'balance' => 0.0,
        ],
    ];

    private $transactions = [
        'user@mail.ru' => [
            [
                'id' => 0,
                'created_at' => "2025-05-10T10:09:04.963Z",
                'type' => 'deposit',
                'amount' => 1259.99,
            ],
            [   // Чтобы был хотя бы один "купленный" курс
                'id' => 1,
                'created_at' => "2025-05-10T10:09:04.963Z",
                'type' => 'payment',
                'amount' => 0.00,
                'course_code' => 'ros2-course'
            ]
        ],
        'admin@mail.ru' => [
            [
                'id' => 2,
                'created_at' => "2025-05-10T10:09:04.963Z",
                'type' => 'deposit',
                'amount' => 99999.99,
            ],
        ],
        'new_user@mail.ru' => [],
    ];

    private $courses = [
        'python-junior' => [
            "title" => "Python Junior",
            "code" => "python-junior",
            "type" => 'rent',
            "price" => 299.99
        ],
        'introduction-to-neural-networks' => [
            "title" => "Introduction to Neural Networks",
            "code" => "introduction-to-neural-networks",
            "type" => 'rent',
            "price" => 500.00
        ],
        'industrial-web-development' => [
            "title" => "Industrial WEB-development",
            "code" => "industrial-web-development",
            "type" => 'pay',
            "price" => 850.00
        ],
        'basics-of-computer-vision' => [
            "title" => "Basics of Computer Vision",
            "code" => "basics-of-computer-vision",
            "type" => 'pay',
            "price" => 350.99
        ],
        'ros2-course' => [
            "title" => "ROS2 Course",
            "code" => "ros2-course",
            "type" => 'free',
            "price" => 0.00
        ],
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
            'exp' => (new DateTime())->setTime('1', '0', '0', '0')->getTimestamp(),
        ];

        $payload = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha512', "$header.$payload", $signing_key, true));
        return "$header.$payload.$signature";
    }

    public function auth(string $username, string $password): array
    {
        $foundUser = null;
        foreach ($this->users as $user) {
            if ($user['email'] === $username) {
                if ($user['password'] === $password) {
                    $foundUser = $username;
                    $token = $this->generateToken($username, (array)$user['roles']);
                } else {
                    throw new InvalidCredentialsException('Invalid password');
                }
            }
        }

        if (!$foundUser) {
            throw new InvalidCredentialsException('User with given username not found');
        }

        return [
            'user' => $foundUser,
            'token' => $token,
            'refresh_token' => $token   // Заглушка
        ];
    }

    public function register(string $username, string $password): User
    {
        foreach ($this->users as $user) {
            if ($user['email'] === $username && $username !== 'new_user@mail.ru') {
                throw new InvalidCredentialsException('User with this email already exists');
            }
        }
        $token = $this->generateToken($username, ['ROLE_USER']);

        $user = new User();
        $user->setApiToken($token);
        $user->setRefreshToken($token); // Заглушка
        $user->setBalance(0.0);

        return $user;
    }

    public function getCurrentUser(string $token): User
    {
        try {
            $user = new User();
            $user->setApiToken($token);
            $user->setRefreshToken($token);
            $user->fromApiToken();
            $user->setBalance($this->users[$user->getUserIdentifier()]['balance']);
            return $user;
        } catch (Exception $e) {
            throw new AuthenticationException('Invalid JWT token');
        }
    }

    public function refreshToken(User $user): User
    {
        return $user;
    }

    public function coursesList(): array
    {
        $courses = [];
        foreach ($this->courses as $course) {
            $data[] = $course;
        }
        return $courses;
    }

    public function courseInfoByCode(string $courseCode): array
    {
        return $this->courses[$courseCode];
    }

    public function isCourseAvailable(string $token, string $courseCode): bool|string
    {
        if ($token == null) {
            return false;
        }

        $user = new User();
        $user->setApiToken($token);
        $user->setRefreshToken($token); // Заглушка
        $user->fromApiToken();
        $user->setBalance($this->users[$user->getUserIdentifier()]['balance']);

        $userTransactions = $this->transactions[$user->getUserIdentifier()];

        foreach ($userTransactions as $transaction) {
            if ($transaction['type'] == 'payment'
                && isset($transaction['course_code'])
                && $transaction['course_code'] == $courseCode) {
                if (isset($transaction['expires_at'])) {
                    $expiresAt = new DateTime($transaction['expires_at']);
                    return $expiresAt->format('d.m.Y');
                } else {
                    return true;
                }
            }
        }

        return false;
    }

    public function payCourse(string $token, string $courseCode): bool
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        $user = new User();
        $user->setApiToken($token);
        $user->setRefreshToken($token); // Заглушка
        $user->fromApiToken();
        $user->setBalance($this->users[$user->getUserIdentifier()]['balance']);

        $username = $user->getUserIdentifier();
        $course = $this->courseInfoByCode($courseCode);
        if ($course['price'] <= $user->getBalance()) {
            $now = new DateTime();
            $this->transactions[$username][] = [
                'id' => count($this->transactions[$username]),
                'created_at' => $now->format('Y-m-d'),
                'type' => 'payment',
                'amount' => $course['price'],
                'course_code' => $courseCode,
                'expires_at' => $now->modify('+1 week')->format('Y-m-d')
            ];
            $this->users[$username]['balance'] -= $course['price'];
            return true;
        } else {
            throw new NotEnoughBalanceException();
        }
    }

    public function isEnoughBalance(string $token, string $courseCode): bool
    {
        if ($token == null) {
            return false;
        }

        $user = new User();
        $user->setApiToken($token);
        $user->setRefreshToken($token); // Заглушка
        $user->fromApiToken();
        $user->setBalance($this->users[$user->getUserIdentifier()]['balance']);

        $course = $this->courseInfoByCode($courseCode);

        if ($course['type'] == 'free' || $course['price'] <= $user->getBalance()) {
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

        $user = new User();
        $user->setApiToken($token);
        $user->setRefreshToken($token); // Заглушка
        $user->fromApiToken();
        $user->setBalance($this->users[$user->getUserIdentifier()]['balance']);

        $userTransactions = $this->transactions[$user->getUserIdentifier()];

        // Сортировка по убыванию даты
        usort($userTransactions, function ($a, $b) {
            return strtotime($b['created_at']) <=> strtotime($a['created_at']);
        });

        return $userTransactions;
    }

    public function createCourse(string $token, CourseDto $courseDto)
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        try {
            $this->courses[$courseDto->code] = [
                'title' => $courseDto->title,
                'code' => $courseDto->code,
                'type' => $courseDto->type,
                'price' => $courseDto->price
            ];
            return;
        } catch (Exception $e) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }
    }

    public function editCourse(string $token, string $courseCode, CourseDto $courseDto)
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        try {
            $this->courses[$courseCode] = [
                'title' => $courseDto->title,
                'code' => $courseDto->code,
                'type' => $courseDto->type,
                'price' => $courseDto->price
            ];
            return;
        } catch (Exception $e) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }
    }

    public function deleteCourse(string $token, string $courseCode)
    {
        if ($token == null) {
            throw new Exception("Missing token");
        }

        try {
            unset($this->courses[$courseCode]);
            return;
        } catch (Exception $e) {
            throw new BillingUnavailableException('Service is temporarily unavailable. Try again later.');
        }
    }
}
