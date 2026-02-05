<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Exceptions;

use Exception;

class AccountNotFoundException extends Exception
{
    public function __construct(string $email)
    {
        parent::__construct("No account found for email: {$email}");
    }
}
