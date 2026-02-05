<?php

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\AccountDriver;
use Daikazu\LaravelFrontdoor\Exceptions\RegistrationNotSupportedException;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Daikazu\LaravelFrontdoor\Support\RegistrationField;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

it('reports registration disabled by default', function () {
    // registration.enabled defaults to false
    config(['frontdoor.registration.enabled' => false]);
    expect(Frontdoor::registrationEnabled())->toBeFalse();
});

it('reports registration enabled with testing driver when config enabled', function () {
    config(['frontdoor.registration.enabled' => true]);

    // Testing driver implements CreatableAccountDriver
    expect(Frontdoor::registrationEnabled())->toBeTrue();
});

it('reports registration disabled when driver does not support creation', function () {
    config(['frontdoor.registration.enabled' => true]);

    // Use a read-only driver that does NOT implement CreatableAccountDriver
    $readOnlyDriver = new class implements AccountDriver
    {
        public function findByEmail(string $email): ?AccountData
        {
            return null;
        }

        public function exists(string $email): bool
        {
            return false;
        }
    };

    Frontdoor::accounts()->extend('readonly', fn () => $readOnlyDriver);
    config(['frontdoor.accounts.driver' => 'readonly']);

    expect(Frontdoor::registrationEnabled())->toBeFalse();
});

it('throws RegistrationNotSupportedException when registration is disabled for register', function () {
    config(['frontdoor.registration.enabled' => false]);

    expect(fn () => Frontdoor::register('new@example.com', ['name' => 'Test']))
        ->toThrow(RegistrationNotSupportedException::class);
});

it('throws RegistrationNotSupportedException when registration is disabled for requestEmailVerification', function () {
    config(['frontdoor.registration.enabled' => false]);

    expect(fn () => Frontdoor::requestEmailVerification('new@example.com'))
        ->toThrow(RegistrationNotSupportedException::class);
});

it('throws RegistrationNotSupportedException when driver does not support creation', function () {
    config(['frontdoor.registration.enabled' => true]);

    $readOnlyDriver = new class implements AccountDriver
    {
        public function findByEmail(string $email): ?AccountData
        {
            return null;
        }

        public function exists(string $email): bool
        {
            return false;
        }
    };

    Frontdoor::accounts()->extend('readonly', fn () => $readOnlyDriver);
    config(['frontdoor.accounts.driver' => 'readonly']);

    expect(fn () => Frontdoor::register('new@example.com', ['name' => 'Test']))
        ->toThrow(RegistrationNotSupportedException::class);
});

it('returns registration fields from testing driver', function () {
    config(['frontdoor.registration.enabled' => true]);

    $fields = Frontdoor::registrationFields();

    expect($fields)->toBeArray()
        ->and($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(RegistrationField::class)
        ->and($fields[0]->name)->toBe('name')
        ->and($fields[0]->required)->toBeTrue();
});

it('throws ValidationException when required registration data is missing', function () {
    config(['frontdoor.registration.enabled' => true]);

    Frontdoor::register('new@example.com', []);
})->throws(ValidationException::class);

it('validates registration data against field rules', function () {
    config(['frontdoor.registration.enabled' => true]);

    try {
        Frontdoor::register('new@example.com', ['name' => '']);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }
});
