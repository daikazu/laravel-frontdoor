<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LoginFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
        public readonly string $reason,
    ) {}
}
