<?php

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Contracts\Auth\Authenticatable;

it('implements Authenticatable', function () {
    $account = new SimpleAccountData(
        id: '123',
        name: 'Jane Doe',
        email: 'jane@example.com',
    );
    $identity = new FrontdoorIdentity($account);
    expect($identity)->toBeInstanceOf(Authenticatable::class);
});

it('returns auth identifier', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);
    expect($identity->getAuthIdentifier())->toBe('123');
    expect($identity->getAuthIdentifierName())->toBe('id');
});

it('proxies account data methods', function () {
    $account = new SimpleAccountData(
        id: '123',
        name: 'Jane Doe',
        email: 'jane@example.com',
        phone: '+1-555-0100',
    );
    $identity = new FrontdoorIdentity($account);
    expect($identity->getId())->toBe('123');
    expect($identity->getName())->toBe('Jane Doe');
    expect($identity->getEmail())->toBe('jane@example.com');
    expect($identity->getPhone())->toBe('+1-555-0100');
});

it('returns null for password methods', function () {
    $account = new SimpleAccountData(id: '1', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);
    expect($identity->getAuthPassword())->toBe('');
    expect($identity->getRememberToken())->toBeNull();
    expect($identity->getRememberTokenName())->toBe('');
});

it('serializes to array', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);
    expect($identity->toArray())->toBe($account->toArray());
});

it('supports property access for built-in fields', function () {
    $account = new SimpleAccountData(
        id: '123',
        name: 'Jane Doe',
        email: 'jane@example.com',
        phone: '+1-555-0100',
        avatarUrl: 'https://example.com/avatar.jpg',
    );
    $identity = new FrontdoorIdentity($account);

    expect($identity->id)->toBe('123');
    expect($identity->name)->toBe('Jane Doe');
    expect($identity->email)->toBe('jane@example.com');
    expect($identity->phone)->toBe('+1-555-0100');
    expect($identity->avatar_url)->toBe('https://example.com/avatar.jpg');
    expect($identity->avatarUrl)->toBe('https://example.com/avatar.jpg');
    expect($identity->initial)->toBe('J');
    expect($identity->metadata)->toBe([]);
});

it('supports property access for metadata keys', function () {
    $account = new SimpleAccountData(
        id: '1',
        name: 'Jane',
        email: 'jane@example.com',
        metadata: ['role' => 'admin', 'company' => 'Acme'],
    );
    $identity = new FrontdoorIdentity($account);

    expect($identity->role)->toBe('admin');
    expect($identity->company)->toBe('Acme');
    expect($identity->unknown_key)->toBeNull();
});

it('supports isset for built-in fields and metadata keys', function () {
    $account = new SimpleAccountData(
        id: '1',
        name: 'Jane',
        email: 'jane@example.com',
        metadata: ['role' => 'admin'],
    );
    $identity = new FrontdoorIdentity($account);

    expect(isset($identity->email))->toBeTrue();
    expect(isset($identity->name))->toBeTrue();
    expect(isset($identity->role))->toBeTrue();
    expect(isset($identity->missing))->toBeFalse();
});
