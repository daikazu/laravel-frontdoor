<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Events;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Illuminate\Foundation\Events\Dispatchable;

class AccountRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly AccountData $account,
    ) {}
}
