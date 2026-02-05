<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Contracts;

interface AccountDriver
{
    /**
     * Find an account by email address.
     *
     * @return AccountData|null Null if account doesn't exist
     */
    public function findByEmail(string $email): ?AccountData;

    /**
     * Check if an email is valid for authentication.
     */
    public function exists(string $email): bool;
}
