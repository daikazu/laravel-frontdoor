<?php

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\CreatableAccountDriver;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Daikazu\LaravelFrontdoor\Livewire\LoginFlow;
use Daikazu\LaravelFrontdoor\Support\RegistrationField;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;

uses()->beforeEach(function () {
    if (! app()->providerIsLoaded(LivewireServiceProvider::class)) {
        app()->register(LivewireServiceProvider::class);
        \Livewire\Livewire::addNamespace('frontdoor', classNamespace: 'Daikazu\\LaravelFrontdoor\\Livewire');
    }
})->in(__FILE__);

beforeEach(function () {
    Cache::flush();
    Mail::fake();

    $this->mockDriver = new class implements CreatableAccountDriver
    {
        /** @var array<string, AccountData> */
        public array $accounts = [];

        public function __construct()
        {
            $this->accounts['jane@example.com'] = new SimpleAccountData(
                id: '1', name: 'Jane Doe', email: 'jane@example.com'
            );
        }

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
                id: 'new-'.count($this->accounts),
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

// --- Email step ---

it('starts at email step', function () {
    Livewire::test(LoginFlow::class)
        ->assertSet('step', 'email');
});

it('submitEmail with valid existing email transitions to otp step', function () {
    Livewire::test(LoginFlow::class)
        ->set('email', 'jane@example.com')
        ->call('submitEmail')
        ->assertSet('step', 'otp')
        ->assertSet('resendCountdown', 60);

    Mail::assertSent(\Daikazu\LaravelFrontdoor\Mail\OtpMail::class);
});

it('submitEmail with unknown email and registration enabled shows registration prompt', function () {
    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->call('submitEmail')
        ->assertSet('step', 'email')
        ->assertSet('showRegistrationPrompt', true);
});

it('submitEmail with unknown email and registration disabled shows error', function () {
    config(['frontdoor.registration.enabled' => false]);

    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->call('submitEmail')
        ->assertSet('step', 'email')
        ->assertSet('errorMessage', 'No account found with this email address.');
});

// --- Register action ---

it('register sends verification OTP and transitions to otp', function () {
    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->call('register')
        ->assertSet('step', 'otp')
        ->assertSet('isRegistering', true)
        ->assertSet('resendCountdown', 60);

    Mail::assertSent(\Daikazu\LaravelFrontdoor\Mail\OtpMail::class);
});

// --- OTP step ---

it('submitCode with correct code in login flow redirects', function () {
    $code = Frontdoor::otp()->generate('jane@example.com');

    Livewire::test(LoginFlow::class)
        ->set('email', 'jane@example.com')
        ->set('step', 'otp')
        ->set('code', $code)
        ->call('submitCode')
        ->assertRedirect();
});

it('submitCode with correct code in registration flow transitions to register step', function () {
    $code = Frontdoor::otp()->generate('newuser@example.com');

    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->set('step', 'otp')
        ->set('isRegistering', true)
        ->set('code', $code)
        ->call('submitCode')
        ->assertSet('step', 'register')
        ->assertSet('registrationFields', fn ($fields) => count($fields) === 1 && $fields[0]['name'] === 'name');
});

it('submitCode with incorrect code shows error', function () {
    Frontdoor::otp()->generate('jane@example.com');

    Livewire::test(LoginFlow::class)
        ->set('email', 'jane@example.com')
        ->set('step', 'otp')
        ->set('code', '000000')
        ->call('submitCode')
        ->assertSet('errorMessage', 'Invalid or expired code. Please try again.');
});

it('too many verification attempts resets to email step', function () {
    Frontdoor::otp()->generate('jane@example.com');

    $component = Livewire::test(LoginFlow::class)
        ->set('email', 'jane@example.com')
        ->set('step', 'otp');

    // incrementAttempts hits the limiter then checks tooManyAttempts(key, 5).
    // tooManyAttempts returns true when attempts >= 5, so the 5th wrong attempt throws.
    for ($i = 0; $i < 4; $i++) {
        $component->set('code', '000000')->call('submitCode');
    }

    $component->set('code', '000000')
        ->call('submitCode')
        ->assertSet('step', 'email')
        ->assertSet('errorMessage', 'Too many attempts. Please request a new code.');
});

// --- Registration step ---

it('submitRegistration with valid data logs in and redirects', function () {
    // First go through the full registration flow
    $code = Frontdoor::otp()->generate('newuser@example.com');

    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->set('step', 'register')
        ->set('isRegistering', true)
        ->set('registrationFields', [
            ['name' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'rules' => ['string', 'max:255'], 'options' => []],
        ])
        ->set('registrationData', ['name' => 'New User'])
        ->call('submitRegistration')
        ->assertRedirect();
});

it('submitRegistration with invalid data shows validation errors', function () {
    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->set('step', 'register')
        ->set('isRegistering', true)
        ->set('registrationFields', [
            ['name' => 'name', 'label' => 'Full name', 'type' => 'text', 'required' => true, 'rules' => ['string', 'max:255'], 'options' => []],
        ])
        ->set('registrationData', [])
        ->call('submitRegistration')
        ->assertHasErrors('name');
});

// --- Error paths ---

it('submitEmail shows error when too many OTP requests', function () {
    // Exhaust the OTP rate limit (max 5 per decay period)
    for ($i = 0; $i < 5; $i++) {
        Frontdoor::otp()->generate('jane@example.com');
    }

    Livewire::test(LoginFlow::class)
        ->set('email', 'jane@example.com')
        ->call('submitEmail')
        ->assertSet('step', 'email')
        ->assertSet('errorMessage', fn ($msg) => str_contains($msg, 'Too many attempts'));
});

it('register shows error when registration not supported', function () {
    config(['frontdoor.registration.enabled' => false]);

    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->call('register')
        ->assertSet('errorMessage', 'Registration is not available at this time.');
});

it('register shows error when too many OTP requests', function () {
    // Exhaust the OTP rate limit
    for ($i = 0; $i < 5; $i++) {
        Frontdoor::otp()->generate('newuser@example.com');
    }

    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->call('register')
        ->assertSet('errorMessage', fn ($msg) => str_contains($msg, 'Too many attempts'));
});

it('submitRegistration shows error when registration not supported', function () {
    config(['frontdoor.registration.enabled' => false]);

    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->set('step', 'register')
        ->set('isRegistering', true)
        ->set('registrationData', ['name' => 'Test'])
        ->call('submitRegistration')
        ->assertSet('errorMessage', 'Registration is not available at this time.');
});

it('submitCode with incorrect code in registration flow shows error', function () {
    Frontdoor::otp()->generate('newuser@example.com');

    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->set('step', 'otp')
        ->set('isRegistering', true)
        ->set('code', '000000')
        ->call('submitCode')
        ->assertSet('errorMessage', 'Invalid or expired code. Please try again.');
});

it('resendCode shows error for unknown account in login flow', function () {
    Livewire::test(LoginFlow::class)
        ->set('email', 'unknown@example.com')
        ->set('step', 'otp')
        ->call('resendCode')
        ->assertSet('errorMessage', 'No account found with this email address.');
});

it('resendCode shows error when too many OTP requests', function () {
    // Exhaust OTP rate limit
    for ($i = 0; $i < 5; $i++) {
        Frontdoor::otp()->generate('jane@example.com');
    }

    Livewire::test(LoginFlow::class)
        ->set('email', 'jane@example.com')
        ->set('step', 'otp')
        ->call('resendCode')
        ->assertSet('errorMessage', fn ($msg) => str_contains($msg, 'Too many attempts'));
});

// --- Resend & navigation ---

it('resendCode in login flow re-requests OTP', function () {
    // First trigger an OTP
    Livewire::test(LoginFlow::class)
        ->set('email', 'jane@example.com')
        ->call('submitEmail')
        ->call('resendCode')
        ->assertSet('resendCountdown', 60);

    Mail::assertSent(\Daikazu\LaravelFrontdoor\Mail\OtpMail::class);
});

it('resendCode in registration flow re-requests verification', function () {
    Livewire::test(LoginFlow::class)
        ->set('email', 'newuser@example.com')
        ->call('register')
        ->call('resendCode')
        ->assertSet('resendCountdown', 60);

    Mail::assertSent(\Daikazu\LaravelFrontdoor\Mail\OtpMail::class);
});

it('goBack resets all state', function () {
    Livewire::test(LoginFlow::class)
        ->set('email', 'jane@example.com')
        ->set('step', 'otp')
        ->set('code', '123456')
        ->set('errorMessage', 'Some error')
        ->set('showRegistrationPrompt', true)
        ->set('isRegistering', true)
        ->set('registrationData', ['name' => 'test'])
        ->set('registrationFields', [['name' => 'test']])
        ->call('goBack')
        ->assertSet('step', 'email')
        ->assertSet('code', '')
        ->assertSet('errorMessage', null)
        ->assertSet('showRegistrationPrompt', false)
        ->assertSet('isRegistering', false)
        ->assertSet('registrationData', [])
        ->assertSet('registrationFields', []);
});
