<?php

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\CreatableAccountDriver;
use Daikazu\LaravelFrontdoor\Events\AccountRegistered;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Daikazu\LaravelFrontdoor\Mail\OtpMail;
use Daikazu\LaravelFrontdoor\Mail\WelcomeMail;
use Daikazu\LaravelFrontdoor\Support\RegistrationField;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
    Event::fake();

    // Set up a creatable mock driver
    $this->mockDriver = new class implements CreatableAccountDriver
    {
        /** @var array<string, AccountData> */
        public array $accounts = [];

        public function findByEmail(string $email): ?AccountData
        {
            return $this->accounts[$email] ?? null;
        }

        public function exists(string $email): bool
        {
            return isset($this->accounts[$email]);
        }

        public function registrationFields(): array
        {
            return [
                new RegistrationField(
                    name: 'name',
                    label: 'Full name',
                    type: 'text',
                    required: true,
                    rules: ['string', 'max:255'],
                ),
            ];
        }

        public function create(string $email, array $data): AccountData
        {
            $account = new SimpleAccountData(
                id: 'reg-'.count($this->accounts),
                name: $data['name'] ?? explode('@', $email)[0],
                email: $email,
            );
            $this->accounts[$email] = $account;

            return $account;
        }
    };

    Frontdoor::accounts()->extend('mock', fn () => $this->mockDriver);
    config(['frontdoor.accounts.driver' => 'mock']);
    config(['frontdoor.registration.enabled' => true]);
});

it('sends verification OTP email via requestEmailVerification', function () {
    $code = Frontdoor::requestEmailVerification('newuser@example.com');

    expect($code)->toHaveLength(6);
    expect($code)->toMatch('/^\d{6}$/');

    Mail::assertSent(OtpMail::class, function ($mail) {
        return $mail->hasTo('newuser@example.com');
    });
});

it('verifies email only without logging in', function () {
    $code = Frontdoor::requestEmailVerification('newuser@example.com');

    $result = Frontdoor::verifyEmailOnly('newuser@example.com', $code);

    expect($result)->toBeTrue();
    expect(auth('frontdoor')->check())->toBeFalse();
});

it('rejects incorrect OTP in verifyEmailOnly', function () {
    Frontdoor::requestEmailVerification('newuser@example.com');

    $result = Frontdoor::verifyEmailOnly('newuser@example.com', '000000');

    expect($result)->toBeFalse();
});

it('registers a new account, auto-logs in, and sends welcome email', function () {
    $account = Frontdoor::register('newuser@example.com', ['name' => 'New User']);

    expect($account)->toBeInstanceOf(AccountData::class);
    expect($account->getEmail())->toBe('newuser@example.com');
    expect($account->getName())->toBe('New User');

    // Should be auto-logged in
    expect(auth('frontdoor')->check())->toBeTrue();
    expect(auth('frontdoor')->user()->getEmail())->toBe('newuser@example.com');

    Mail::assertSent(WelcomeMail::class, function ($mail) {
        return $mail->hasTo('newuser@example.com');
    });

    Event::assertDispatched(AccountRegistered::class, function ($event) {
        return $event->account->getEmail() === 'newuser@example.com';
    });
});

it('falls through to requestEmailVerification when account already exists during register', function () {
    // Pre-populate the mock driver with an existing account
    $this->mockDriver->accounts['existing@example.com'] = new SimpleAccountData(
        id: 'existing-1',
        name: 'Existing User',
        email: 'existing@example.com',
    );

    $account = Frontdoor::register('existing@example.com', ['name' => 'Whatever']);

    expect($account)->toBeInstanceOf(AccountData::class);
    expect($account->getEmail())->toBe('existing@example.com');

    // Should send normal OTP mail (falls through to requestOtp via requestEmailVerification)
    Mail::assertSent(OtpMail::class, function ($mail) {
        return $mail->hasTo('existing@example.com');
    });
    Mail::assertNotSent(WelcomeMail::class);

    // Should NOT fire AccountRegistered event
    Event::assertNotDispatched(AccountRegistered::class);
});

it('falls through to requestOtp when account already exists during requestEmailVerification', function () {
    $this->mockDriver->accounts['existing@example.com'] = new SimpleAccountData(
        id: 'existing-1',
        name: 'Existing User',
        email: 'existing@example.com',
    );

    $code = Frontdoor::requestEmailVerification('existing@example.com');

    expect($code)->toHaveLength(6);

    // Should send normal OTP mail (not verification-specific, because it falls through to requestOtp)
    Mail::assertSent(OtpMail::class, function ($mail) {
        return $mail->hasTo('existing@example.com');
    });
});

it('creates account with provided name from registration data', function () {
    Frontdoor::register('johndoe@example.com', ['name' => 'John Doe']);

    $account = $this->mockDriver->findByEmail('johndoe@example.com');

    expect($account)->not->toBeNull();
    expect($account->getName())->toBe('John Doe');
    expect($account->getEmail())->toBe('johndoe@example.com');
});

it('returns registration fields from the mock driver', function () {
    $fields = Frontdoor::registrationFields();

    expect($fields)->toHaveCount(1);
    expect($fields[0])->toBeInstanceOf(RegistrationField::class);
    expect($fields[0]->name)->toBe('name');
    expect($fields[0]->label)->toBe('Full name');
    expect($fields[0]->required)->toBeTrue();
});

it('rejects registration with missing required fields', function () {
    Frontdoor::register('newuser@example.com', []);
})->throws(\Illuminate\Validation\ValidationException::class);

it('completes full registration flow: verify email then register', function () {
    // Step 1: Request email verification
    $code = Frontdoor::requestEmailVerification('flow@example.com');
    expect($code)->toHaveLength(6);

    // Step 2: Verify email only (no login)
    $verified = Frontdoor::verifyEmailOnly('flow@example.com', $code);
    expect($verified)->toBeTrue();
    expect(auth('frontdoor')->check())->toBeFalse();

    // Step 3: Register (auto-login + welcome email)
    $account = Frontdoor::register('flow@example.com', ['name' => 'Flow User']);
    expect($account->getName())->toBe('Flow User');
    expect(auth('frontdoor')->check())->toBeTrue();
    expect(auth('frontdoor')->user()->getName())->toBe('Flow User');

    Mail::assertSent(WelcomeMail::class, function ($mail) {
        return $mail->hasTo('flow@example.com');
    });
});

it('sends welcome email with account data', function () {
    Frontdoor::register('newuser@example.com', ['name' => 'New User']);

    Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail) {
        return $mail->hasTo('newuser@example.com')
            && $mail->account !== null
            && $mail->account->getName() === 'New User';
    });
});
