<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Auth;

use Daikazu\LaravelFrontdoor\Contracts\AccountDriver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class FrontdoorUserProvider implements UserProvider
{
    public function __construct(
        protected AccountDriver $accountDriver
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        return null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Not supported
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (! isset($credentials['email']) || ! is_string($credentials['email'])) {
            return null;
        }

        $account = $this->accountDriver->findByEmail($credentials['email']);

        if ($account === null) {
            return null;
        }

        return new FrontdoorIdentity($account);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Not supported - OTP auth
    }
}
