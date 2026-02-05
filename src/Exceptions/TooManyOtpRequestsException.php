<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Exceptions;

use Exception;

class TooManyOtpRequestsException extends Exception
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct("Too many OTP requests. Try again in {$retryAfterSeconds} seconds.");
    }
}
