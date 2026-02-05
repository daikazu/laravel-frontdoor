<?php

use Daikazu\LaravelFrontdoor\Drivers\ConfigAccountDriver;

it('finds account by email', function () {
    $provider = new ConfigAccountDriver([
        'jane@example.com' => [
            'name' => 'Jane Doe',
            'phone' => '+1-555-0100',
        ],
    ]);

    $account = $provider->findByEmail('jane@example.com');

    expect($account)->not->toBeNull();
    expect($account->getName())->toBe('Jane Doe');
    expect($account->getEmail())->toBe('jane@example.com');
    expect($account->getPhone())->toBe('+1-555-0100');
});

it('returns null for unknown email', function () {
    $provider = new ConfigAccountDriver([]);
    expect($provider->findByEmail('unknown@example.com'))->toBeNull();
});

it('normalizes email to lowercase', function () {
    $provider = new ConfigAccountDriver([
        'jane@example.com' => ['name' => 'Jane'],
    ]);

    expect($provider->findByEmail('JANE@EXAMPLE.COM'))->not->toBeNull();
});

it('checks if email exists', function () {
    $provider = new ConfigAccountDriver([
        'jane@example.com' => ['name' => 'Jane'],
    ]);

    expect($provider->exists('jane@example.com'))->toBeTrue();
    expect($provider->exists('unknown@example.com'))->toBeFalse();
});

it('generates name from email if not provided', function () {
    $provider = new ConfigAccountDriver([
        'john.doe@example.com' => [],
    ]);

    $account = $provider->findByEmail('john.doe@example.com');
    expect($account->getName())->toBe('John Doe');
});

it('generates id from email hash if not provided', function () {
    $provider = new ConfigAccountDriver([
        'jane@example.com' => ['name' => 'Jane'],
    ]);

    $account = $provider->findByEmail('jane@example.com');
    expect($account->getId())->toBe(md5('jane@example.com'));
});
