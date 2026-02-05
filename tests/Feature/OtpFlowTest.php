<?php

use Daikazu\LaravelFrontdoor\Exceptions\AccountNotFoundException;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Mail::fake();

    config(['frontdoor.accounts.drivers.testing.users' => [
        'jane@example.com' => ['name' => 'Jane Doe'],
    ]]);
});

it('sends OTP email for valid account', function () {
    Frontdoor::requestOtp('jane@example.com');

    Mail::assertSent(\Daikazu\LaravelFrontdoor\Mail\OtpMail::class, function ($mail) {
        return $mail->hasTo('jane@example.com');
    });
});

it('throws exception for unknown account', function () {
    expect(fn () => Frontdoor::requestOtp('unknown@example.com'))
        ->toThrow(AccountNotFoundException::class);
});

it('verifies correct OTP and logs in user', function () {
    $code = Frontdoor::otp()->generate('jane@example.com');

    $result = Frontdoor::verify('jane@example.com', $code);

    expect($result)->toBeTrue();
    expect(auth('frontdoor')->check())->toBeTrue();
    expect(auth('frontdoor')->user()->getEmail())->toBe('jane@example.com');
});

it('rejects incorrect OTP', function () {
    Frontdoor::otp()->generate('jane@example.com');

    $result = Frontdoor::verify('jane@example.com', '000000');

    expect($result)->toBeFalse();
    expect(auth('frontdoor')->check())->toBeFalse();
});
