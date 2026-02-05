<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Drivers;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\CreatableAccountDriver;
use Daikazu\LaravelFrontdoor\Support\RegistrationField;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Contracts\Cache\Repository;

class CacheAccountDriver implements CreatableAccountDriver
{
    private const CACHE_KEY = 'frontdoor:accounts';

    /**
     * @param  array<string, array<string, mixed>>  $seedUsers
     */
    public function __construct(
        protected Repository $cache,
        protected array $seedUsers = [],
    ) {}

    public function findByEmail(string $email): ?AccountData
    {
        $normalized = strtolower($email);

        $users = $this->allUsers();

        if (! isset($users[$normalized])) {
            return null;
        }

        $data = $users[$normalized];

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
        return isset($this->allUsers()[strtolower($email)]);
    }

    public function registrationFields(): array
    {
        return [
            new RegistrationField(
                name: 'name',
                label: 'Full name',
                type: 'text',
                required: true,
                rules: ['string', 'max:255'],
            ),
        ];
    }

    public function create(string $email, array $data): AccountData
    {
        $normalized = strtolower($email);

        $userData = [
            'id' => md5($normalized),
            'name' => $data['name'] ?? $this->nameFromEmail($normalized),
        ];

        if (isset($data['phone'])) {
            $userData['phone'] = $data['phone'];
        }

        $users = $this->allUsers();
        $users[$normalized] = $userData;
        $this->cache->put(self::CACHE_KEY, $users);

        return new SimpleAccountData(
            id: $userData['id'],
            name: $userData['name'],
            email: $normalized,
            phone: $userData['phone'] ?? null,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function allUsers(): array
    {
        /** @var array<string, array<string, mixed>> $cached */
        $cached = $this->cache->get(self::CACHE_KEY, []);

        return array_merge($this->seedUsers, $cached);
    }

    protected function nameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0];

        return str($local)->replace(['.', '_', '-'], ' ')->title()->toString();
    }
}
