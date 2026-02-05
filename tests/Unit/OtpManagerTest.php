<?php

use Daikazu\LaravelFrontdoor\Events\LoginFailed;
use Daikazu\LaravelFrontdoor\Events\OtpRequested;
use Daikazu\LaravelFrontdoor\Events\OtpVerified;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyOtpRequestsException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyVerificationAttemptsException;
use Daikazu\LaravelFrontdoor\Otp\CacheOtpStore;
use Daikazu\LaravelFrontdoor\Otp\OtpManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('frontdoor:rate:'.hash('sha256', 'test@example.com'));
    RateLimiter::clear('frontdoor:verify:'.hash('sha256', 'test@example.com'));
    Event::fake();
});

function createOtpManager(): OtpManager
{
    return new OtpManager(
        new CacheOtpStore(Cache::store(), 'frontdoor:otp:'),
        [
            'length' => 6,
            'ttl' => 600,
            'rate_limit' => [
                'max_attempts' => 5,
                'decay_seconds' => 300,
            ],
        ]
    );
}

it('generates 6-digit code', function () {
    $manager = createOtpManager();
    $code = $manager->generate('test@example.com');
    expect($code)->toHaveLength(6);
    expect($code)->toMatch('/^\d{6}$/');
});

it('fires OtpRequested event on generate', function () {
    $manager = createOtpManager();
    $manager->generate('test@example.com');
    Event::assertDispatched(OtpRequested::class, fn ($e) => $e->email === 'test@example.com');
});

it('verifies correct code', function () {
    $manager = createOtpManager();
    $code = $manager->generate('test@example.com');
    expect($manager->verify('test@example.com', $code))->toBeTrue();
});

it('fires OtpVerified event on successful verify', function () {
    $manager = createOtpManager();
    $code = $manager->generate('test@example.com');
    $manager->verify('test@example.com', $code);
    Event::assertDispatched(OtpVerified::class);
});

it('rejects incorrect code', function () {
    $manager = createOtpManager();
    $manager->generate('test@example.com');
    expect($manager->verify('test@example.com', '000000'))->toBeFalse();
});

it('fires LoginFailed event on incorrect code', function () {
    $manager = createOtpManager();
    $manager->generate('test@example.com');
    $manager->verify('test@example.com', '000000');
    Event::assertDispatched(LoginFailed::class, fn ($e) => $e->reason === 'invalid_code');
});

it('invalidates code after single use', function () {
    $manager = createOtpManager();
    $code = $manager->generate('test@example.com');
    $manager->verify('test@example.com', $code);
    expect($manager->verify('test@example.com', $code))->toBeFalse();
});

it('checks if OTP is pending', function () {
    $manager = createOtpManager();
    expect($manager->hasPending('test@example.com'))->toBeFalse();
    $manager->generate('test@example.com');
    expect($manager->hasPending('test@example.com'))->toBeTrue();
});

it('rate limits OTP generation', function () {
    $manager = createOtpManager();
    for ($i = 0; $i < 5; $i++) {
        $manager->generate('test@example.com');
    }
    expect(fn () => $manager->generate('test@example.com'))
        ->toThrow(TooManyOtpRequestsException::class);
});

it('throws after too many verification attempts', function () {
    $manager = createOtpManager();
    $manager->generate('test@example.com');
    for ($i = 0; $i < 5; $i++) {
        try {
            $manager->verify('test@example.com', '000000');
        } catch (TooManyVerificationAttemptsException $e) {
            expect($i)->toBe(4);

            return;
        }
    }
    $this->fail('Expected TooManyVerificationAttemptsException');
});
