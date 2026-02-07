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

it('returns true for guest when not authenticated', function () {
    expect(Auth::guard('frontdoor')->guest())->toBeTrue();
});

it('returns false for guest when authenticated', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);

    expect(Auth::guard('frontdoor')->guest())->toBeFalse();
});

it('validate always returns false', function () {
    expect(Auth::guard('frontdoor')->validate(['email' => 'jane@example.com']))->toBeFalse();
});

it('hasUser returns false when no user set', function () {
    expect(Auth::guard('frontdoor')->hasUser())->toBeFalse();
});

it('hasUser returns true after login', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);

    expect(Auth::guard('frontdoor')->hasUser())->toBeTrue();
});

it('setUser accepts FrontdoorIdentity', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    $guard = Auth::guard('frontdoor');
    $guard->setUser($identity);

    expect($guard->hasUser())->toBeTrue();
    expect($guard->user())->toBe($identity);
});

it('setUser throws for non-FrontdoorIdentity', function () {
    $user = new class implements \Illuminate\Contracts\Auth\Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return '1';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };

    expect(fn () => Auth::guard('frontdoor')->setUser($user))
        ->toThrow(\InvalidArgumentException::class);
});
