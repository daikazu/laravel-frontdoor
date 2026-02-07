<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Concerns;

trait HasPropertyAccess
{
    abstract public function getId(): string;

    abstract public function getName(): string;

    abstract public function getEmail(): string;

    abstract public function getPhone(): ?string;

    abstract public function getAvatarUrl(): ?string;

    /**
     * @return array<string, mixed>
     */
    abstract public function getMetadata(): array;

    abstract public function getInitial(): string;

    public function __get(string $name): mixed
    {
        return match ($name) {
            'id' => $this->getId(),
            'name' => $this->getName(),
            'email' => $this->getEmail(),
            'phone' => $this->getPhone(),
            'avatar_url', 'avatarUrl' => $this->getAvatarUrl(),
            'metadata' => $this->getMetadata(),
            'initial' => $this->getInitial(),
            default => $this->getMetadata()[$name] ?? null,
        };
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'id', 'name', 'email', 'phone', 'avatar_url', 'avatarUrl', 'metadata', 'initial' => true,
            default => array_key_exists($name, $this->getMetadata()),
        };
    }
}
