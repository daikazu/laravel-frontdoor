<?php

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Daikazu\LaravelFrontdoor\Auth\FrontdoorUserProvider;
use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\AccountDriver;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

beforeEach(function () {
    $this->driver = new class implements AccountDriver
    {
        public function findByEmail(string $email): ?AccountData
        {
            if ($email === 'jane@example.com') {
                return new SimpleAccountData(id: '1', name: 'Jane Doe', email: 'jane@example.com');
            }

            return null;
        }

        public function exists(string $email): bool
        {
            return $email === 'jane@example.com';
        }
    };

    $this->provider = new FrontdoorUserProvider($this->driver);
});

it('retrieveById returns null', function () {
    expect($this->provider->retrieveById('1'))->toBeNull();
});

it('retrieveByToken returns null', function () {
    expect($this->provider->retrieveByToken('1', 'token'))->toBeNull();
});

it('updateRememberToken does nothing', function () {
    $identity = new FrontdoorIdentity(
        new SimpleAccountData(id: '1', name: 'Jane', email: 'jane@example.com')
    );

    $this->provider->updateRememberToken($identity, 'token');

    // No exception means success
    expect(true)->toBeTrue();
});

it('retrieveByCredentials with valid email returns FrontdoorIdentity', function () {
    $result = $this->provider->retrieveByCredentials(['email' => 'jane@example.com']);

    expect($result)->toBeInstanceOf(FrontdoorIdentity::class);
    expect($result->getEmail())->toBe('jane@example.com');
});

it('retrieveByCredentials without email key returns null', function () {
    expect($this->provider->retrieveByCredentials(['username' => 'jane']))->toBeNull();
});

it('retrieveByCredentials with non-existent email returns null', function () {
    expect($this->provider->retrieveByCredentials(['email' => 'unknown@example.com']))->toBeNull();
});

it('validateCredentials returns false', function () {
    $identity = new FrontdoorIdentity(
        new SimpleAccountData(id: '1', name: 'Jane', email: 'jane@example.com')
    );

    expect($this->provider->validateCredentials($identity, ['password' => 'secret']))->toBeFalse();
});

it('rehashPasswordIfRequired does nothing', function () {
    $identity = new FrontdoorIdentity(
        new SimpleAccountData(id: '1', name: 'Jane', email: 'jane@example.com')
    );

    $this->provider->rehashPasswordIfRequired($identity, ['password' => 'secret']);

    // No exception means success
    expect(true)->toBeTrue();
});
