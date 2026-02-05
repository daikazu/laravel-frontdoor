<?php

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\AccountDriver;
use Daikazu\LaravelFrontdoor\Drivers\CacheAccountDriver;
use Daikazu\LaravelFrontdoor\Support\AccountManager;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

beforeEach(function () {
    config(['frontdoor.accounts.driver' => 'testing']);
    config(['frontdoor.accounts.drivers.testing.users' => [
        'jane@example.com' => ['name' => 'Jane Doe'],
    ]]);
});

it('creates testing driver by default', function () {
    $manager = app(AccountManager::class);
    expect($manager->driver())->toBeInstanceOf(CacheAccountDriver::class);
});

it('returns default driver name', function () {
    $manager = app(AccountManager::class);
    expect($manager->getDefaultDriver())->toBe('testing');
});

it('allows extending with custom drivers', function () {
    $manager = app(AccountManager::class);

    $manager->extend('custom', fn () => new CacheAccountDriver(
        app('cache.store'),
        ['custom@example.com' => ['name' => 'Custom User']],
    ));

    $driver = $manager->driver('custom');
    expect($driver->exists('custom@example.com'))->toBeTrue();
});

it('resolves a named driver mapped to a class in config', function () {
    $provider = new class implements AccountDriver
    {
        public function findByEmail(string $email): ?AccountData
        {
            if ($email === 'mapped@example.com') {
                return new SimpleAccountData(id: '2', name: 'Mapped User', email: $email);
            }

            return null;
        }

        public function exists(string $email): bool
        {
            return $email === 'mapped@example.com';
        }
    };

    app()->instance($provider::class, $provider);
    config(['frontdoor.accounts.driver' => 'mydriver']);
    config(['frontdoor.accounts.drivers.mydriver' => $provider::class]);

    $manager = app(AccountManager::class);
    $account = $manager->driver()->findByEmail('mapped@example.com');

    expect($account)->not->toBeNull();
    expect($account->getName())->toBe('Mapped User');
});

it('resolves a class name as driver', function () {
    $provider = new class implements AccountDriver
    {
        public function findByEmail(string $email): ?AccountData
        {
            if ($email === 'fqcn@example.com') {
                return new SimpleAccountData(id: '1', name: 'FQCN User', email: $email);
            }

            return null;
        }

        public function exists(string $email): bool
        {
            return $email === 'fqcn@example.com';
        }
    };

    app()->instance($provider::class, $provider);
    config(['frontdoor.accounts.driver' => $provider::class]);

    $manager = app(AccountManager::class);
    $account = $manager->driver()->findByEmail('fqcn@example.com');

    expect($account)->not->toBeNull();
    expect($account->getName())->toBe('FQCN User');
});
