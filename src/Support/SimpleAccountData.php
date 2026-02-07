<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Support;

use Daikazu\LaravelFrontdoor\Concerns\HasPropertyAccess;
use Daikazu\LaravelFrontdoor\Contracts\AccountData;

readonly class SimpleAccountData implements AccountData
{
    use HasPropertyAccess;
    /**
     * Create a new simple account data instance.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private string $id,
        private string $name,
        private string $email,
        private ?string $phone = null,
        private ?string $avatarUrl = null,
        private array $metadata = [],
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getInitial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatarUrl,
            'metadata' => $this->metadata,
            'initial' => $this->getInitial(),
        ];
    }

}
