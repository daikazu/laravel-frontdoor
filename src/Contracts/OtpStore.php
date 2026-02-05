<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Contracts;

interface OtpStore
{
    /**
     * Store a hashed OTP for an identifier.
     */
    public function store(string $identifier, string $hashedCode, int $ttl): void;

    /**
     * Retrieve the hashed OTP for an identifier.
     */
    public function get(string $identifier): ?string;

    /**
     * Delete the OTP after successful verification.
     */
    public function forget(string $identifier): void;

    /**
     * Check if identifier has a pending OTP.
     */
    public function has(string $identifier): bool;
}
