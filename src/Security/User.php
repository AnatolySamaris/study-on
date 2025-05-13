<?php

namespace App\Security;

use Exception;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private $email;

    /**
     * @var list<string> The user roles
     */
    private $roles = [];

    private $balance = null;

    private $apiToken = null;

    private $refreshToken = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getBalance(): ?string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): static
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function fromApiToken(): static
    {
        $parts = explode('.', $this->apiToken);

        $payload = json_decode(
            base64_decode($parts[1]),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->setEmail($payload['username']);
        if (is_array($payload['roles'])) {
            $this->setRoles($payload['roles']);
        } else {
            $this->setRoles(json_decode($payload['roles'], true));
        }

        return $this;
    }
}
