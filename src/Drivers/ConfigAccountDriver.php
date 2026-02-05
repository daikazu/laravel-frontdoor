<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Drivers;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\AccountDriver;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

class ConfigAccountDriver implements AccountDriver
{
    /**
     * @param  array<string, array<string, mixed>>  $users
     */
    public function __construct(
        protected array $users
    ) {}

    public function findByEmail(string $email): ?AccountData
    {
        $normalized = strtolower($email);

        if (! isset($this->users[$normalized])) {
            return null;
        }

        $data = $this->users[$normalized];

        return new SimpleAccountData(
            id: $data['id'] ?? md5($normalized),
            name: $data['name'] ?? $this->nameFromEmail($normalized),
            email: $normalized,
            phone: $data['phone'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function exists(string $email): bool
    {
        return isset($this->users[strtolower($email)]);
    }

    protected function nameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0];

        return str($local)->replace(['.', '_', '-'], ' ')->title()->toString();
    }
}
