<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Contracts;

use Daikazu\LaravelFrontdoor\Support\RegistrationField;

interface CreatableAccountDriver extends AccountDriver
{
    /**
     * Return the form fields required for registration.
     *
     * @return RegistrationField[]
     */
    public function registrationFields(): array;

    /**
     * Create a new account from an email and registration form data.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(string $email, array $data): AccountData;
}
