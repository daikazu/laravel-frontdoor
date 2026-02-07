<?php

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\CreatableAccountDriver;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Daikazu\LaravelFrontdoor\Support\RegistrationField;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Mail::fake();

    $this->mockDriver = new class implements CreatableAccountDriver
    {
        /** @var array<string, AccountData> */
        public array $accounts = [
            'jane@example.com' => null,
        ];

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

// --- Login flow ---

it('GET /frontdoor/login returns 200', function () {
    $this->get(route('frontdoor.login'))
        ->assertStatus(200);
});

it('POST /frontdoor/login with valid email sends OTP and redirects to verify', function () {
    $this->post(route('frontdoor.send-otp'), ['email' => 'jane@example.com'])
        ->assertRedirect(route('frontdoor.verify'));

    expect(session('frontdoor_email'))->toBe('jane@example.com');

    Mail::assertSent(\Daikazu\LaravelFrontdoor\Mail\OtpMail::class);
});

it('POST /frontdoor/login with unknown email and registration enabled redirects to register', function () {
    $this->post(route('frontdoor.send-otp'), ['email' => 'newuser@example.com'])
        ->assertRedirect(route('frontdoor.show-register', ['email' => 'newuser@example.com']));
});

it('POST /frontdoor/login with unknown email and registration disabled shows error', function () {
    config(['frontdoor.registration.enabled' => false]);

    $this->post(route('frontdoor.send-otp'), ['email' => 'newuser@example.com'])
        ->assertSessionHasErrors('email');
});

// --- Verify flow ---

it('GET /frontdoor/verify without session email redirects to login', function () {
    $this->get(route('frontdoor.verify'))
        ->assertRedirect(route('frontdoor.login'));
});

it('GET /frontdoor/verify with session email returns 200', function () {
    $this->withSession(['frontdoor_email' => 'jane@example.com'])
        ->get(route('frontdoor.verify'))
        ->assertStatus(200);
});

it('POST /frontdoor/verify with correct code logs in and redirects', function () {
    $code = Frontdoor::otp()->generate('jane@example.com');

    $this->withSession(['frontdoor_email' => 'jane@example.com'])
        ->post(route('frontdoor.verify-otp'), ['code' => $code])
        ->assertRedirect('/');

    expect(auth('frontdoor')->check())->toBeTrue();
});

it('POST /frontdoor/verify with incorrect code shows error', function () {
    Frontdoor::otp()->generate('jane@example.com');

    $this->withSession(['frontdoor_email' => 'jane@example.com'])
        ->post(route('frontdoor.verify-otp'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
});

it('POST /frontdoor/verify without session email redirects to login', function () {
    $this->post(route('frontdoor.verify-otp'), ['code' => '123456'])
        ->assertRedirect(route('frontdoor.login'));
});

it('POST /frontdoor/verify in registration mode verifies email and redirects to complete', function () {
    $code = Frontdoor::otp()->generate('newuser@example.com');

    $this->withSession([
        'frontdoor_email' => 'newuser@example.com',
        'frontdoor_registering' => true,
    ])
        ->post(route('frontdoor.verify-otp'), ['code' => $code])
        ->assertRedirect(route('frontdoor.show-register-complete'));

    expect(session('frontdoor_email_verified'))->toBeTrue();
});

// --- Registration flow ---

it('GET /frontdoor/register with registration disabled redirects to login', function () {
    config(['frontdoor.registration.enabled' => false]);

    $this->get(route('frontdoor.show-register'))
        ->assertRedirect(route('frontdoor.login'));
});

it('GET /frontdoor/register with registration enabled returns 200', function () {
    $this->get(route('frontdoor.show-register'))
        ->assertStatus(200);
});

it('POST /frontdoor/register sends verification OTP and stores session flags', function () {
    $this->post(route('frontdoor.register'), ['email' => 'newuser@example.com'])
        ->assertRedirect(route('frontdoor.verify'));

    expect(session('frontdoor_email'))->toBe('newuser@example.com');
    expect(session('frontdoor_registering'))->toBeTrue();

    Mail::assertSent(\Daikazu\LaravelFrontdoor\Mail\OtpMail::class);
});

it('GET /frontdoor/register/complete without verified email redirects to login', function () {
    $this->get(route('frontdoor.show-register-complete'))
        ->assertRedirect(route('frontdoor.login'));
});

it('GET /frontdoor/register/complete with verified email returns 200', function () {
    $this->withSession([
        'frontdoor_email' => 'newuser@example.com',
        'frontdoor_email_verified' => true,
    ])
        ->get(route('frontdoor.show-register-complete'))
        ->assertStatus(200);
});

it('POST /frontdoor/register/complete creates account, logs in, and clears session', function () {
    $this->withSession([
        'frontdoor_email' => 'newuser@example.com',
        'frontdoor_email_verified' => true,
    ])
        ->post(route('frontdoor.register-complete'), ['name' => 'New User'])
        ->assertRedirect('/');

    expect(auth('frontdoor')->check())->toBeTrue();
    expect(auth('frontdoor')->user()->getEmail())->toBe('newuser@example.com');
});

it('POST /frontdoor/register/complete with invalid data shows validation errors', function () {
    $this->withSession([
        'frontdoor_email' => 'newuser@example.com',
        'frontdoor_email_verified' => true,
    ])
        ->post(route('frontdoor.register-complete'), [])
        ->assertSessionHasErrors('name');
});

// --- Rate limiting & error paths ---

it('POST /frontdoor/login shows error when too many OTP requests', function () {
    // Exhaust OTP rate limit (max 5 per decay period)
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('frontdoor.send-otp'), ['email' => 'jane@example.com']);
    }

    $this->post(route('frontdoor.send-otp'), ['email' => 'jane@example.com'])
        ->assertSessionHasErrors('email');
});

it('POST /frontdoor/verify with too many attempts redirects to login', function () {
    Frontdoor::otp()->generate('jane@example.com');

    // Exhaust verification attempts (5 wrong = TooManyVerificationAttemptsException on 5th)
    for ($i = 0; $i < 4; $i++) {
        $this->withSession(['frontdoor_email' => 'jane@example.com'])
            ->post(route('frontdoor.verify-otp'), ['code' => '000000']);
    }

    $this->withSession(['frontdoor_email' => 'jane@example.com'])
        ->post(route('frontdoor.verify-otp'), ['code' => '000000'])
        ->assertRedirect(route('frontdoor.login'))
        ->assertSessionHasErrors('email');
});

it('POST /frontdoor/verify with incorrect code in registration mode shows error', function () {
    Frontdoor::otp()->generate('newuser@example.com');

    $this->withSession([
        'frontdoor_email' => 'newuser@example.com',
        'frontdoor_registering' => true,
    ])
        ->post(route('frontdoor.verify-otp'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
});

it('POST /frontdoor/register shows error when registration not supported', function () {
    config(['frontdoor.registration.enabled' => false]);

    $this->post(route('frontdoor.register'), ['email' => 'newuser@example.com'])
        ->assertSessionHasErrors('email');
});

it('POST /frontdoor/register shows error when too many OTP requests', function () {
    // Exhaust OTP rate limit
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('frontdoor.register'), ['email' => 'newuser@example.com']);
    }

    $this->post(route('frontdoor.register'), ['email' => 'newuser@example.com'])
        ->assertSessionHasErrors('email');
});

it('POST /frontdoor/register/complete without session redirects to login', function () {
    $this->post(route('frontdoor.register-complete'), ['name' => 'Test'])
        ->assertRedirect(route('frontdoor.login'));
});

// --- Logout ---

it('POST /frontdoor/logout logs out and redirects', function () {
    // Log in first
    Frontdoor::loginAs('jane@example.com');
    expect(auth('frontdoor')->check())->toBeTrue();

    $this->post(route('frontdoor.logout'))
        ->assertRedirect('/');

    expect(auth('frontdoor')->check())->toBeFalse();
});
