<?php

namespace App\Security;

use App\Service\BillingClient;
use DateTimeZone;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private BillingClient $billingClient
    ) {
    }

    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me.
     *
     * If you're not using these features, you do not need to implement
     * this method.
     *
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier($identifier): UserInterface
    {
        $user = $this->billingClient->getCurrentUser($identifier);

        if (!$user) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        // Проверяем наличие refresh token на всякий случай
        if (!$user->getRefreshToken()) {
            throw new UnsupportedUserException('Refresh token not found');
        }

        $payload_str = explode('.', $user->getApiToken())[1];
        $payload = json_decode(
            base64_decode($payload_str),
            true
        );

        $expiredTime = new \DateTime('@' . $payload['exp']);
        $now = new \DateTime();

        if ($expiredTime->getTimezone()->getName() !== $now->getTimezone()->getName()) {
            $expiredTime->setTimezone(new DateTimeZone('Europe/Moscow'));
            $now->setTimezone(new DateTimeZone('Europe/Moscow'));
        }

        return $expiredTime <= $now ? $this->billingClient->refreshToken($user) : $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    /**
     * Upgrades the hashed password of a user, typically for using a better hash algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // TODO: when hashed passwords are in use, this method should:
        // 1. persist the new password in the user storage
        // 2. update the $user object with $user->setPassword($newHashedPassword);
    }
}
