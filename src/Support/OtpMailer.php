<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Support;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\OtpMailable;
use Daikazu\LaravelFrontdoor\Mail\WelcomeMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class OtpMailer
{
    public function send(string $email, string $code, ?AccountData $account = null): void
    {
        /** @var class-string<OtpMailable&Mailable> $mailableClass */
        $mailableClass = config('frontdoor.mail.mailable', \Daikazu\LaravelFrontdoor\Mail\OtpMail::class);
        $ttlMinutes = (int) ceil(config('frontdoor.otp.ttl', 600) / 60);

        /** @var OtpMailable&Mailable $mailable */
        $mailable = app($mailableClass)
            ->setCode($code)
            ->setExpiresInMinutes($ttlMinutes);

        if ($account !== null) {
            $mailable->setAccount($account);
        }

        Mail::to($email)->send($mailable);
    }

    public function sendVerification(string $email, string $code): void
    {
        /** @var class-string<OtpMailable&Mailable> $mailableClass */
        $mailableClass = config('frontdoor.mail.verification_mailable', \Daikazu\LaravelFrontdoor\Mail\OtpMail::class);
        $ttlMinutes = (int) ceil(config('frontdoor.otp.ttl', 600) / 60);

        /** @var OtpMailable&Mailable $mailable */
        $mailable = app($mailableClass)
            ->setCode($code)
            ->setExpiresInMinutes($ttlMinutes);

        Mail::to($email)->send($mailable);
    }

    public function sendWelcome(string $email, ?AccountData $account = null): void
    {
        /** @var class-string<WelcomeMail> $mailableClass */
        $mailableClass = config('frontdoor.mail.welcome_mailable', WelcomeMail::class);

        $mailable = app($mailableClass);

        if ($account !== null) {
            $mailable->setAccount($account);
        }

        Mail::to($email)->send($mailable);
    }
}
