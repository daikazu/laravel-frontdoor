<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OtpRequested
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
    ) {}
}
