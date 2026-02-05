<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Daikazu\LaravelFrontdoor\Support\AccountManager accounts()
 * @method static \Daikazu\LaravelFrontdoor\Otp\OtpManager otp()
 * @method static string requestOtp(string $email)
 * @method static string requestEmailVerification(string $email)
 * @method static bool verifyEmailOnly(string $email, string $code)
 * @method static bool verify(string $email, string $code)
 * @method static bool loginAs(string $email)
 * @method static bool registrationEnabled()
 * @method static \Daikazu\LaravelFrontdoor\Support\RegistrationField[] registrationFields()
 * @method static \Daikazu\LaravelFrontdoor\Contracts\AccountData register(string $email, array $data = [])
 *
 * @see \Daikazu\LaravelFrontdoor\Frontdoor
 */
class Frontdoor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Daikazu\LaravelFrontdoor\Frontdoor::class;
    }
}
