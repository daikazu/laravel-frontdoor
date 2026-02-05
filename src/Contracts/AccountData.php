<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Contracts;

interface AccountData
{
    /**
     * Get the unique identifier for the account.
     */
    public function getId(): string;

    /**
     * Get the account holder's name.
     */
    public function getName(): string;

    /**
     * Get the account holder's email address.
     */
    public function getEmail(): string;

    /**
     * Get the account holder's phone number.
     */
    public function getPhone(): ?string;

    /**
     * Get the URL to the account holder's avatar image.
     */
    public function getAvatarUrl(): ?string;

    /**
     * Get additional metadata associated with the account.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Get the first character of the account holder's name in uppercase.
     */
    public function getInitial(): string;

    /**
     * Convert the account data to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
