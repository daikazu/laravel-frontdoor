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
