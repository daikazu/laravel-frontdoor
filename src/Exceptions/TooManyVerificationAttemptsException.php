<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Exceptions;

use Exception;

class TooManyVerificationAttemptsException extends Exception
{
    public function __construct()
    {
        parent::__construct('Too many verification attempts. Please request a new code.');
    }
}
