<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OtpVerified
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
    ) {}
}
