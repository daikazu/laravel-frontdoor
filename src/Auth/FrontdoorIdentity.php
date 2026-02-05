<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Auth;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Illuminate\Contracts\Auth\Authenticatable;

class FrontdoorIdentity implements Authenticatable
{
    public function __construct(
        protected AccountData $account
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->account->getId();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Not supported - session-only auth
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    // Proxy methods for AccountData
    public function getId(): string
    {
        return $this->account->getId();
    }

    public function getName(): string
    {
        return $this->account->getName();
    }

    public function getEmail(): string
    {
        return $this->account->getEmail();
    }

    public function getPhone(): ?string
    {
        return $this->account->getPhone();
    }

    public function getAvatarUrl(): ?string
    {
        return $this->account->getAvatarUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->account->getMetadata();
    }

    public function getInitial(): string
    {
        return $this->account->getInitial();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->account->toArray();
    }

    public function getAccountData(): AccountData
    {
        return $this->account;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'id' => $this->account->getId(),
            'name' => $this->account->getName(),
            'email' => $this->account->getEmail(),
            'phone' => $this->account->getPhone(),
            'avatar_url', 'avatarUrl' => $this->account->getAvatarUrl(),
            'metadata' => $this->account->getMetadata(),
            'initial' => $this->account->getInitial(),
            default => $this->account->getMetadata()[$name] ?? null,
        };
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'id', 'name', 'email', 'phone', 'avatar_url', 'avatarUrl', 'metadata', 'initial' => true,
            default => array_key_exists($name, $this->account->getMetadata()),
        };
    }
}
