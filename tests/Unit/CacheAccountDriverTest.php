<?php

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Drivers\CacheAccountDriver;
use Daikazu\LaravelFrontdoor\Support\RegistrationField;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->driver = new CacheAccountDriver(app('cache.store'));
});

it('registrationFields returns name field', function () {
    $fields = $this->driver->registrationFields();

    expect($fields)->toHaveCount(1);
    expect($fields[0])->toBeInstanceOf(RegistrationField::class);
    expect($fields[0]->name)->toBe('name');
    expect($fields[0]->label)->toBe('Full name');
    expect($fields[0]->type)->toBe('text');
    expect($fields[0]->required)->toBeTrue();
    expect($fields[0]->rules)->toBe(['string', 'max:255']);
});

it('create stores account and returns AccountData', function () {
    $account = $this->driver->create('newuser@example.com', ['name' => 'New User']);

    expect($account)->toBeInstanceOf(AccountData::class);
    expect($account->getEmail())->toBe('newuser@example.com');
    expect($account->getName())->toBe('New User');
    expect($account->getId())->toBe(md5('newuser@example.com'));
});

it('create derives name from email when not provided', function () {
    $account = $this->driver->create('john.doe@example.com', []);

    expect($account->getName())->toBe('John Doe');
});

it('create includes phone when provided', function () {
    $account = $this->driver->create('jane@example.com', [
        'name' => 'Jane',
        'phone' => '+1234567890',
    ]);

    expect($account->getPhone())->toBe('+1234567890');
});

it('created account is findable afterward', function () {
    $this->driver->create('findme@example.com', ['name' => 'Find Me']);

    $found = $this->driver->findByEmail('findme@example.com');

    expect($found)->not->toBeNull();
    expect($found->getName())->toBe('Find Me');
    expect($found->getEmail())->toBe('findme@example.com');
});

it('create normalizes email to lowercase', function () {
    $account = $this->driver->create('UPPER@Example.COM', ['name' => 'Upper']);

    expect($account->getEmail())->toBe('upper@example.com');

    $found = $this->driver->findByEmail('upper@example.com');
    expect($found)->not->toBeNull();
});
