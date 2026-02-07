<?php

declare(strict_types=1);

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

it('implements AccountData contract', function (): void {
    $account = new SimpleAccountData(
        id: '123',
        name: 'John Doe',
        email: 'john@example.com',
        phone: '+1234567890',
        avatarUrl: 'https://example.com/avatar.jpg',
        metadata: ['role' => 'admin']
    );

    expect($account)->toBeInstanceOf(AccountData::class);
    expect($account->getId())->toBe('123');
    expect($account->getName())->toBe('John Doe');
    expect($account->getEmail())->toBe('john@example.com');
    expect($account->getPhone())->toBe('+1234567890');
    expect($account->getAvatarUrl())->toBe('https://example.com/avatar.jpg');
    expect($account->getMetadata())->toBe(['role' => 'admin']);
});

it('extracts initial from name', function (): void {
    $account = new SimpleAccountData(
        id: '123',
        name: 'John Doe',
        email: 'john@example.com'
    );

    expect($account->getInitial())->toBe('J');
});

it('handles unicode names', function (): void {
    $account = new SimpleAccountData(
        id: '123',
        name: '李明',
        email: 'li@example.com'
    );

    expect($account->getInitial())->toBe('李');
});

it('converts to array', function (): void {
    $account = new SimpleAccountData(
        id: '123',
        name: 'John Doe',
        email: 'john@example.com',
        phone: '+1234567890',
        avatarUrl: 'https://example.com/avatar.jpg',
        metadata: ['role' => 'admin']
    );

    $expected = [
        'id' => '123',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'avatar_url' => 'https://example.com/avatar.jpg',
        'metadata' => ['role' => 'admin'],
        'initial' => 'J',
    ];

    expect($account->toArray())->toBe($expected);
});

it('allows nullable phone and avatar', function (): void {
    $account = new SimpleAccountData(
        id: '123',
        name: 'John Doe',
        email: 'john@example.com'
    );

    expect($account->getPhone())->toBeNull();
    expect($account->getAvatarUrl())->toBeNull();
    expect($account->getMetadata())->toBe([]);
});

it('supports property access for built-in fields', function (): void {
    $account = new SimpleAccountData(
        id: '123',
        name: 'Jane Doe',
        email: 'jane@example.com',
        phone: '+1-555-0100',
        avatarUrl: 'https://example.com/avatar.jpg',
        metadata: ['role' => 'admin'],
    );

    expect($account->id)->toBe('123');
    expect($account->name)->toBe('Jane Doe');
    expect($account->email)->toBe('jane@example.com');
    expect($account->phone)->toBe('+1-555-0100');
    expect($account->avatar_url)->toBe('https://example.com/avatar.jpg');
    expect($account->avatarUrl)->toBe('https://example.com/avatar.jpg');
    expect($account->initial)->toBe('J');
    expect($account->metadata)->toBe(['role' => 'admin']);
});

it('supports property access for metadata keys', function (): void {
    $account = new SimpleAccountData(
        id: '1',
        name: 'Jane',
        email: 'jane@example.com',
        metadata: ['role' => 'admin', 'company' => 'Acme'],
    );

    expect($account->role)->toBe('admin');
    expect($account->company)->toBe('Acme');
    expect($account->unknown_key)->toBeNull();
});

it('supports isset for built-in fields and metadata keys', function (): void {
    $account = new SimpleAccountData(
        id: '1',
        name: 'Jane',
        email: 'jane@example.com',
        metadata: ['role' => 'admin'],
    );

    expect(isset($account->email))->toBeTrue();
    expect(isset($account->role))->toBeTrue();
    expect(isset($account->missing))->toBeFalse();
});
