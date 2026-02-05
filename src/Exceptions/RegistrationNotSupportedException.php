<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Exceptions;

use Exception;

class RegistrationNotSupportedException extends Exception
{
    public function __construct()
    {
        parent::__construct('Registration is not supported: either it is disabled in config or the account driver does not support account creation.');
    }
}
