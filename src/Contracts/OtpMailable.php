<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Contracts;

interface OtpMailable
{
    public function setCode(string $code): static;

    public function setAccount(AccountData $account): static;

    public function setExpiresInMinutes(int $minutes): static;
}
