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
        /** @var int $ttl */
        $ttl = config('frontdoor.otp.ttl', 600);
        $ttlMinutes = (int) ceil($ttl / 60);

        /** @var class-string $mailableClass */
        $mailableClass = config('frontdoor.mail.mailable', \Daikazu\LaravelFrontdoor\Mail\OtpMail::class);

        $instance = app($mailableClass);

        if (! $instance instanceof OtpMailable || ! $instance instanceof Mailable) {
            throw new \InvalidArgumentException('Mailable must implement OtpMailable and extend Mailable');
        }

        $instance->setCode($code);
        $instance->setExpiresInMinutes($ttlMinutes);

        if ($account !== null) {
            $instance->setAccount($account);
        }

        Mail::to($email)->send($instance);
    }

    public function sendVerification(string $email, string $code): void
    {
        /** @var int $ttl */
        $ttl = config('frontdoor.otp.ttl', 600);
        $ttlMinutes = (int) ceil($ttl / 60);

        /** @var class-string $mailableClass */
        $mailableClass = config('frontdoor.mail.verification_mailable', \Daikazu\LaravelFrontdoor\Mail\OtpMail::class);

        $instance = app($mailableClass);

        if (! $instance instanceof OtpMailable || ! $instance instanceof Mailable) {
            throw new \InvalidArgumentException('Mailable must implement OtpMailable and extend Mailable');
        }

        $instance->setCode($code);
        $instance->setExpiresInMinutes($ttlMinutes);

        Mail::to($email)->send($instance);
    }

    public function sendWelcome(string $email, ?AccountData $account = null): void
    {
        /** @var class-string $mailableClass */
        $mailableClass = config('frontdoor.mail.welcome_mailable', WelcomeMail::class);

        $instance = app($mailableClass);

        if (! $instance instanceof WelcomeMail) {
            throw new \InvalidArgumentException('Welcome mailable must extend WelcomeMail');
        }

        if ($account !== null) {
            $instance->setAccount($account);
        }

        Mail::to($email)->send($instance);
    }
}
