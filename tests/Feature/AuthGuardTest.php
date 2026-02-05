<?php

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    session()->flush();
});

it('checks if user is authenticated', function () {
    expect(Auth::guard('frontdoor')->check())->toBeFalse();
});

it('returns null user when not authenticated', function () {
    expect(Auth::guard('frontdoor')->user())->toBeNull();
});

it('returns null id when not authenticated', function () {
    expect(Auth::guard('frontdoor')->id())->toBeNull();
});

it('logs in user via identity', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);

    expect(Auth::guard('frontdoor')->check())->toBeTrue();
    expect(Auth::guard('frontdoor')->user())->toBeInstanceOf(FrontdoorIdentity::class);
    expect(Auth::guard('frontdoor')->id())->toBe('123');
});

it('persists identity across requests via session', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);

    // Simulate new request by clearing the guard's cached user
    Auth::guard('frontdoor')->setUser(null);

    // User should still be authenticated from session
    expect(Auth::guard('frontdoor')->check())->toBeTrue();
});

it('logs out user', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);
    Auth::guard('frontdoor')->logout();

    expect(Auth::guard('frontdoor')->check())->toBeFalse();
    expect(Auth::guard('frontdoor')->user())->toBeNull();
});

it('works with auth helper', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);

    expect(auth('frontdoor')->check())->toBeTrue();
    expect(auth('frontdoor')->user()->getName())->toBe('Jane');
    expect(auth('frontdoor')->id())->toBe('123');
});
