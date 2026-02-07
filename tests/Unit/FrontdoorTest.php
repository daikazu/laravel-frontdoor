<?php

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

it('loginAs with valid email returns true and logs in', function () {
    $result = Frontdoor::loginAs('jane@example.com');

    expect($result)->toBeTrue();
    expect(auth('frontdoor')->check())->toBeTrue();
    expect(auth('frontdoor')->user()->getEmail())->toBe('jane@example.com');
});

it('loginAs with unknown email returns false', function () {
    $result = Frontdoor::loginAs('unknown@example.com');

    expect($result)->toBeFalse();
    expect(auth('frontdoor')->check())->toBeFalse();
});

it('verify returns false when account deleted after OTP generated', function () {
    // Generate OTP for a valid user
    $code = Frontdoor::otp()->generate('jane@example.com');

    // Remove the user from config to simulate deletion
    config(['frontdoor.accounts.drivers.testing.users' => []]);
    // Flush cache to remove any cached account data
    Cache::tags([]); // ensure we're working with fresh state

    // Re-resolve the account manager so it picks up empty users
    // The simplest approach: just override with no users
    config(['frontdoor.accounts.drivers.testing.users' => []]);

    $result = Frontdoor::verify('jane@example.com', $code);

    expect($result)->toBeFalse();
});
