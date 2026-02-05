<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Events;

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Illuminate\Foundation\Events\Dispatchable;

class LoginSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly FrontdoorIdentity $identity,
    ) {}
}
