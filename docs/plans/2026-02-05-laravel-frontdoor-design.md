# Laravel Frontdoor Design Document

**Date:** 2026-02-05
**Status:** Approved
**Package:** daikazu/laravel-frontdoor
**Laravel:** 12+
**PHP:** 8.4+

---

## Overview

Laravel Frontdoor is a drop-in OTP authentication system. Users log in with their email—no passwords. The package fetches account data from a configurable provider (CRM, API, config file, etc.), sends an OTP via Laravel Mail, and establishes a session-based identity that works with Laravel's `auth()` helpers.

### Key Principles

- Zero database migrations required
- Works with `auth()->user()`, `auth()->check()`, `@auth` directives
- Provider-agnostic: bring your own account data source
- UI is optional but polished out of the box

### Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Login UI | Modal-first | Keeps users on current page, better UX |
| Identity storage | Session-only | No migrations, lightweight, host app can sync via events |
| Default account provider | Config-based | Zero setup, version-controllable, obvious driver pattern |
| OTP storage | Cache-only | Natural TTL, no migrations, ephemeral by nature |
| UI components | Livewire + Blade fallback | Best UX with Livewire, broader compatibility with fallback |

---

## User Journey

1. User clicks "Login" in navigation → modal opens
2. User enters email → package checks provider for account → sends OTP email
3. User enters 6-digit code → package verifies against hashed cache entry
4. Success → `FrontdoorIdentity` stored in session, modal shows confirmation
5. Modal closes → nav button now shows avatar + name with dropdown menu
6. User clicks "Logout" → session cleared, back to "Login" button

## Developer Journey

1. Install package, publish config
2. Set mail driver (already configured in most apps)
3. Optionally implement custom `AccountProvider` for their data source
4. Drop `<x-frontdoor::nav-login />` into navigation
5. Customize views/mailable if desired

---

## Package Structure

```
laravel-frontdoor/
├── config/
│   └── frontdoor.php
├── database/
│   └── factories/              # For testing only
├── resources/
│   ├── views/
│   │   ├── components/
│   │   │   ├── nav-login.blade.php
│   │   │   ├── avatar.blade.php
│   │   │   └── modal.blade.php
│   │   ├── livewire/
│   │   │   ├── login-flow.blade.php
│   │   │   ├── email-step.blade.php
│   │   │   ├── otp-step.blade.php
│   │   │   └── success-step.blade.php
│   │   ├── blade/              # Alpine.js fallback versions
│   │   │   ├── login-modal.blade.php
│   │   │   ├── email-form.blade.php
│   │   │   ├── otp-form.blade.php
│   │   │   └── success.blade.php
│   │   └── mail/
│   │       └── otp.blade.php
│   └── lang/
│       └── en/
│           └── frontdoor.php
├── routes/
│   └── frontdoor.php           # Publishable, optional
├── src/
│   ├── Auth/
│   │   ├── FrontdoorGuard.php
│   │   ├── FrontdoorIdentity.php
│   │   └── FrontdoorUserProvider.php
│   ├── Commands/
│   │   └── InstallCommand.php
│   ├── Contracts/
│   │   ├── AccountProvider.php
│   │   ├── AccountData.php
│   │   ├── OtpStore.php
│   │   └── OtpMailable.php
│   ├── Drivers/
│   │   └── ConfigAccountProvider.php
│   ├── Events/
│   │   ├── OtpRequested.php
│   │   ├── OtpVerified.php
│   │   ├── LoginSucceeded.php
│   │   ├── LoginFailed.php
│   │   └── LogoutSucceeded.php
│   ├── Exceptions/
│   │   ├── AccountNotFoundException.php
│   │   ├── TooManyOtpRequestsException.php
│   │   └── TooManyVerificationAttemptsException.php
│   ├── Facades/
│   │   └── Frontdoor.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── AuthController.php  # Blade fallback routes
│   │   └── Middleware/
│   │       └── FrontdoorAuthenticated.php
│   ├── Livewire/
│   │   └── LoginFlow.php
│   ├── Mail/
│   │   └── OtpMail.php
│   ├── Otp/
│   │   ├── OtpManager.php
│   │   └── CacheOtpStore.php
│   ├── Support/
│   │   ├── AccountManager.php      # Driver manager
│   │   ├── Avatar.php              # Deterministic gradient helper
│   │   ├── AvatarStyle.php
│   │   ├── AvatarData.php
│   │   ├── SimpleAccountData.php
│   │   ├── OtpMailer.php
│   │   └── ServiceDetector.php     # Detects Livewire availability
│   ├── View/
│   │   └── Components/
│   │       ├── NavLogin.php
│   │       └── Avatar.php
│   ├── Frontdoor.php               # Main service class
│   └── FrontdoorServiceProvider.php
└── tests/
    ├── Feature/
    │   ├── OtpFlowTest.php
    │   ├── AuthGuardTest.php
    │   ├── LivewireLoginFlowTest.php
    │   ├── BladeLoginFlowTest.php
    │   ├── NavLoginComponentTest.php
    │   └── LogoutTest.php
    ├── Unit/
    │   ├── AccountManagerTest.php
    │   ├── ConfigProviderTest.php
    │   ├── OtpManagerTest.php
    │   ├── AvatarTest.php
    │   └── SimpleAccountDataTest.php
    └── TestCase.php
```

---

## Configuration

```php
<?php

// config/frontdoor.php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    */
    'guard' => 'frontdoor',

    /*
    |--------------------------------------------------------------------------
    | Account Provider
    |--------------------------------------------------------------------------
    | Driver for fetching account data. Ships with 'config' driver.
    | Create custom drivers by implementing AccountProvider contract.
    */
    'accounts' => [
        'driver' => env('FRONTDOOR_ACCOUNT_DRIVER', 'config'),

        'drivers' => [
            'config' => [
                'users' => [
                    // 'jane@example.com' => [
                    //     'name' => 'Jane Doe',
                    //     'phone' => '+1-555-0100',
                    //     'metadata' => ['role' => 'admin'],
                    // ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP Settings
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => 6,
        'ttl' => 600,              // 10 minutes in seconds
        'cache_store' => null,     // null = default cache store
        'cache_prefix' => 'frontdoor:otp:',

        'rate_limit' => [
            'max_attempts' => 5,   // Per email per window
            'decay_seconds' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Settings
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'mailable' => \Daikazu\LaravelFrontdoor\Mail\OtpMail::class,
        'from' => [
            'address' => env('FRONTDOOR_MAIL_FROM', env('MAIL_FROM_ADDRESS')),
            'name' => env('FRONTDOOR_MAIL_FROM_NAME', env('MAIL_FROM_NAME')),
        ],
        'subject' => 'Your login code',
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'mode' => 'modal',         // 'modal' or 'page'
        'prefer_livewire' => true, // Falls back to Blade+Alpine if unavailable
        'login_route' => '/login', // For 'page' mode or fallback

        'nav' => [
            'login_label' => 'Login',
            'account_label' => 'Account',
            'logout_label' => 'Logout',
            'account_route' => null, // null = no account link
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Avatar Settings
    |--------------------------------------------------------------------------
    */
    'avatar' => [
        'algorithm' => 'gradient', // 'gradient' or 'solid'
        'saturation' => 65,        // HSL saturation %
        'lightness' => 55,         // HSL lightness %
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'frontdoor',
        'middleware' => ['web'],
    ],

];
```

---

## Contracts

### AccountProvider

```php
<?php

namespace Daikazu\LaravelFrontdoor\Contracts;

interface AccountProvider
{
    /**
     * Find an account by email address.
     *
     * @return AccountData|null Null if account doesn't exist
     */
    public function findByEmail(string $email): ?AccountData;

    /**
     * Check if an email is valid for authentication.
     */
    public function exists(string $email): bool;
}
```

### AccountData

```php
<?php

namespace Daikazu\LaravelFrontdoor\Contracts;

interface AccountData
{
    public function getId(): string;
    public function getName(): string;
    public function getEmail(): string;
    public function getPhone(): ?string;
    public function getAvatarUrl(): ?string;
    public function getMetadata(): array;
    public function getInitial(): string;
    public function toArray(): array;
}
```

### OtpStore

```php
<?php

namespace Daikazu\LaravelFrontdoor\Contracts;

interface OtpStore
{
    public function store(string $identifier, string $hashedCode, int $ttl): void;
    public function get(string $identifier): ?string;
    public function forget(string $identifier): void;
    public function has(string $identifier): bool;
}
```

### OtpMailable

```php
<?php

namespace Daikazu\LaravelFrontdoor\Contracts;

interface OtpMailable
{
    public function setCode(string $code): static;
    public function setAccount(AccountData $account): static;
    public function setExpiresInMinutes(int $minutes): static;
}
```

---

## Driver Manager

The `AccountManager` extends Laravel's `Manager` class for familiar driver-based extensibility.

```php
<?php

namespace Daikazu\LaravelFrontdoor\Support;

use Closure;
use Daikazu\LaravelFrontdoor\Contracts\AccountProvider;
use Daikazu\LaravelFrontdoor\Drivers\ConfigAccountProvider;
use Illuminate\Support\Manager;

class AccountManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('frontdoor.accounts.driver', 'config');
    }

    protected function createConfigDriver(): AccountProvider
    {
        $users = $this->config->get('frontdoor.accounts.drivers.config.users', []);
        return new ConfigAccountProvider($users);
    }
}
```

### Config Account Provider

```php
<?php

namespace Daikazu\LaravelFrontdoor\Drivers;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\AccountProvider;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

class ConfigAccountProvider implements AccountProvider
{
    public function __construct(
        protected array $users
    ) {}

    public function findByEmail(string $email): ?AccountData
    {
        $normalized = strtolower($email);

        if (! isset($this->users[$normalized])) {
            return null;
        }

        $data = $this->users[$normalized];

        return new SimpleAccountData(
            id: $data['id'] ?? md5($normalized),
            name: $data['name'] ?? $this->nameFromEmail($normalized),
            email: $normalized,
            phone: $data['phone'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public function exists(string $email): bool
    {
        return isset($this->users[strtolower($email)]);
    }

    protected function nameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0];
        return str($local)->replace(['.', '_', '-'], ' ')->title()->toString();
    }
}
```

### SimpleAccountData

```php
<?php

namespace Daikazu\LaravelFrontdoor\Support;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;

readonly class SimpleAccountData implements AccountData
{
    public function __construct(
        protected string $id,
        protected string $name,
        protected string $email,
        protected ?string $phone = null,
        protected ?string $avatarUrl = null,
        protected array $metadata = [],
    ) {}

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): ?string { return $this->phone; }
    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function getMetadata(): array { return $this->metadata; }

    public function getInitial(): string
    {
        return strtoupper(mb_substr($this->name, 0, 1));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatarUrl,
            'metadata' => $this->metadata,
        ];
    }
}
```

### Registering Custom Drivers

```php
// In AppServiceProvider::boot()
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;

Frontdoor::accounts()->extend('hubspot', function ($app) {
    return new \App\Frontdoor\HubspotAccountProvider(
        config('services.hubspot.api_key')
    );
});
```

---

## OTP Management

### OtpManager

```php
<?php

namespace Daikazu\LaravelFrontdoor\Otp;

use Daikazu\LaravelFrontdoor\Contracts\OtpStore;
use Daikazu\LaravelFrontdoor\Events\OtpRequested;
use Daikazu\LaravelFrontdoor\Events\OtpVerified;
use Daikazu\LaravelFrontdoor\Events\LoginFailed;
use Illuminate\Support\Facades\RateLimiter;

class OtpManager
{
    public function __construct(
        protected OtpStore $store,
        protected array $config,
    ) {}

    public function generate(string $email): string
    {
        $this->checkRateLimit($email);

        $code = $this->generateCode();
        $hashedCode = $this->hash($code);
        $identifier = $this->identifier($email);

        $this->store->store($identifier, $hashedCode, $this->config['ttl']);

        event(new OtpRequested($email));

        return $code;
    }

    public function verify(string $email, string $code): bool
    {
        $identifier = $this->identifier($email);
        $storedHash = $this->store->get($identifier);

        if (! $storedHash) {
            event(new LoginFailed($email, 'expired'));
            return false;
        }

        if (! $this->verifyHash($code, $storedHash)) {
            $this->incrementAttempts($email);
            event(new LoginFailed($email, 'invalid_code'));
            return false;
        }

        // Single-use: delete after successful verification
        $this->store->forget($identifier);
        $this->clearRateLimit($email);

        event(new OtpVerified($email));

        return true;
    }

    public function hasPending(string $email): bool
    {
        return $this->store->has($this->identifier($email));
    }

    protected function generateCode(): string
    {
        $length = $this->config['length'];
        return str_pad(
            (string) random_int(0, (10 ** $length) - 1),
            $length,
            '0',
            STR_PAD_LEFT
        );
    }

    protected function hash(string $code): string
    {
        return hash_hmac('sha256', $code, config('app.key'));
    }

    protected function verifyHash(string $code, string $hash): bool
    {
        return hash_equals($hash, $this->hash($code));
    }

    protected function identifier(string $email): string
    {
        return hash('sha256', strtolower($email));
    }

    protected function checkRateLimit(string $email): void
    {
        $key = 'frontdoor:rate:' . $this->identifier($email);
        $maxAttempts = $this->config['rate_limit']['max_attempts'];
        $decay = $this->config['rate_limit']['decay_seconds'];

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            throw new \Daikazu\LaravelFrontdoor\Exceptions\TooManyOtpRequestsException($seconds);
        }

        RateLimiter::hit($key, $decay);
    }

    protected function incrementAttempts(string $email): void
    {
        $key = 'frontdoor:verify:' . $this->identifier($email);
        RateLimiter::hit($key, 300);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->store->forget($this->identifier($email));
            throw new \Daikazu\LaravelFrontdoor\Exceptions\TooManyVerificationAttemptsException;
        }
    }

    protected function clearRateLimit(string $email): void
    {
        RateLimiter::clear('frontdoor:rate:' . $this->identifier($email));
        RateLimiter::clear('frontdoor:verify:' . $this->identifier($email));
    }
}
```

### CacheOtpStore

```php
<?php

namespace Daikazu\LaravelFrontdoor\Otp;

use Daikazu\LaravelFrontdoor\Contracts\OtpStore;
use Illuminate\Contracts\Cache\Repository;

class CacheOtpStore implements OtpStore
{
    public function __construct(
        protected Repository $cache,
        protected string $prefix = 'frontdoor:otp:',
    ) {}

    public function store(string $identifier, string $hashedCode, int $ttl): void
    {
        $this->cache->put($this->key($identifier), $hashedCode, $ttl);
    }

    public function get(string $identifier): ?string
    {
        return $this->cache->get($this->key($identifier));
    }

    public function forget(string $identifier): void
    {
        $this->cache->forget($this->key($identifier));
    }

    public function has(string $identifier): bool
    {
        return $this->cache->has($this->key($identifier));
    }

    protected function key(string $identifier): string
    {
        return $this->prefix . $identifier;
    }
}
```

### Security Model

- Codes hashed with HMAC-SHA256 using app key—not reversible
- Email identifiers hashed before use as cache keys—no PII in cache keys
- Single-use: deleted immediately after successful verification
- Dual rate limiting: OTP generation rate + verification attempt rate
- Brute-force protection: too many bad codes invalidates the OTP entirely

---

## Mail

### OtpMail

```php
<?php

namespace Daikazu\LaravelFrontdoor\Mail;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\OtpMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements OtpMailable
{
    use Queueable, SerializesModels;

    public string $code;
    public ?AccountData $account = null;
    public int $expiresInMinutes = 10;

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function setAccount(AccountData $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function setExpiresInMinutes(int $minutes): static
    {
        $this->expiresInMinutes = $minutes;
        return $this;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress(),
            subject: config('frontdoor.mail.subject', 'Your login code'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'frontdoor::mail.otp',
            with: [
                'code' => $this->code,
                'account' => $this->account,
                'expiresInMinutes' => $this->expiresInMinutes,
                'appName' => config('app.name'),
            ],
        );
    }

    protected function fromAddress(): \Illuminate\Mail\Mailables\Address
    {
        return new \Illuminate\Mail\Mailables\Address(
            config('frontdoor.mail.from.address'),
            config('frontdoor.mail.from.name'),
        );
    }
}
```

### Email Template

```blade
{{-- resources/views/mail/otp.blade.php --}}

<x-mail::message>
# Your Login Code

@if($account)
Hello {{ $account->getName() }},
@else
Hello,
@endif

Use the code below to complete your login to **{{ $appName }}**.

<x-mail::panel>
<div style="text-align: center; font-size: 32px; letter-spacing: 8px; font-weight: bold;">
{{ $code }}
</div>
</x-mail::panel>

This code expires in **{{ $expiresInMinutes }} minutes**.

If you didn't request this code, you can safely ignore this email.

Thanks,<br>
{{ $appName }}
</x-mail::message>
```

### Customization Options

1. **Publish and edit the view:** `php artisan vendor:publish --tag=frontdoor-views`
2. **Swap the entire Mailable class:** Set `mail.mailable` in config

---

## UI Components

### Component Inventory

| Component | Type | Purpose |
|-----------|------|---------|
| `<x-frontdoor::nav-login />` | Blade | Nav button: Login or Avatar dropdown |
| `<x-frontdoor::avatar />` | Blade | Deterministic gradient avatar |
| `<x-frontdoor::modal />` | Blade | Modal wrapper (Alpine.js powered) |
| `<livewire:frontdoor::login-flow />` | Livewire | Full OTP flow (email → code → success) |
| `<x-frontdoor::login-modal />` | Blade | Fallback: Alpine-only login modal |

### Basic Usage

```blade
{{-- Navigation --}}
<nav class="flex items-center gap-4">
    <a href="/">Home</a>
    <a href="/pricing">Pricing</a>
    <x-frontdoor::nav-login />
</nav>

{{-- Standalone login --}}
<div class="max-w-md mx-auto mt-20">
    <livewire:frontdoor::login-flow />
</div>

{{-- Custom trigger --}}
<button x-data x-on:click="$dispatch('frontdoor-open')">
    Sign in to continue
</button>
<x-frontdoor::modal />

{{-- Avatar standalone --}}
<x-frontdoor::avatar
    :identifier="$user->email"
    :name="$user->name"
    size="md"
/>

{{-- Protected content --}}
@auth('frontdoor')
    <p>Welcome, {{ auth('frontdoor')->user()->getName() }}</p>
@else
    <x-frontdoor::nav-login label="Sign in to view" />
@endauth
```

### Nav Login Component

```php
<?php

namespace Daikazu\LaravelFrontdoor\View\Components;

use Illuminate\View\Component;

class NavLogin extends Component
{
    public function __construct(
        public ?string $label = null,
        public ?string $accountRoute = null,
        public string $size = 'md',
    ) {
        $this->label ??= config('frontdoor.ui.nav.login_label', 'Login');
        $this->accountRoute ??= config('frontdoor.ui.nav.account_route');
    }

    public function render()
    {
        return view('frontdoor::components.nav-login');
    }
}
```

```blade
{{-- resources/views/components/nav-login.blade.php --}}

@auth('frontdoor')
    @php $identity = auth('frontdoor')->user(); @endphp

    <div
        x-data="{ open: false }"
        x-on:click.outside="open = false"
        class="relative"
    >
        {{-- Avatar trigger --}}
        <button
            x-on:click="open = !open"
            class="flex items-center gap-2 rounded-full p-1 hover:bg-gray-100 transition"
        >
            <x-frontdoor::avatar
                :identifier="$identity->getEmail()"
                :name="$identity->getName()"
                :size="$size"
            />
            <span class="text-sm font-medium text-gray-700 max-w-[120px] truncate">
                {{ $identity->getName() }}
            </span>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Dropdown menu --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg ring-1 ring-black/5 py-1 z-50"
        >
            <div class="px-4 py-2 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-900 truncate">{{ $identity->getName() }}</p>
                <p class="text-xs text-gray-500 truncate">{{ $identity->getEmail() }}</p>
            </div>

            @if($accountRoute)
                <a href="{{ $accountRoute }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    {{ config('frontdoor.ui.nav.account_label', 'Account') }}
                </a>
            @endif

            <form method="POST" action="{{ route('frontdoor.logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    {{ config('frontdoor.ui.nav.logout_label', 'Logout') }}
                </button>
            </form>
        </div>
    </div>
@else
    <button
        x-data
        x-on:click="$dispatch('frontdoor-open')"
        {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition']) }}
    >
        {{ $label }}
    </button>

    <x-frontdoor::modal />
@endauth
```

---

## Deterministic Avatar

### Avatar Helper

```php
<?php

namespace Daikazu\LaravelFrontdoor\Support;

class Avatar
{
    public static function gradient(string $identifier): AvatarStyle
    {
        $hash = sha1(strtolower(trim($identifier)));

        $hue1 = hexdec(substr($hash, 0, 2)) / 255 * 360;
        $hue2 = hexdec(substr($hash, 2, 2)) / 255 * 360;
        $angle = hexdec(substr($hash, 4, 2)) / 255 * 360;

        $saturation = config('frontdoor.avatar.saturation', 65);
        $lightness = config('frontdoor.avatar.lightness', 55);

        // Ensure hues are different enough (min 30° apart)
        if (abs($hue1 - $hue2) < 30) {
            $hue2 = fmod($hue1 + 60, 360);
        }

        $color1 = "hsl({$hue1}, {$saturation}%, {$lightness}%)";
        $color2 = "hsl({$hue2}, {$saturation}%, {$lightness}%)";

        $textColor = $lightness > 55 ? '#1f2937' : '#ffffff';

        return new AvatarStyle(
            gradient: "linear-gradient({$angle}deg, {$color1}, {$color2})",
            textColor: $textColor,
            hue1: $hue1,
            hue2: $hue2,
        );
    }

    public static function initial(string $name): string
    {
        $name = trim($name);

        if (str_contains($name, '@')) {
            $name = explode('@', $name)[0];
        }

        return strtoupper(mb_substr($name, 0, 1));
    }

    public static function make(string $identifier, ?string $name = null): AvatarData
    {
        $style = static::gradient($identifier);
        $initial = static::initial($name ?? $identifier);

        return new AvatarData(
            initial: $initial,
            style: $style,
            identifier: $identifier,
        );
    }
}
```

### AvatarStyle

```php
<?php

namespace Daikazu\LaravelFrontdoor\Support;

readonly class AvatarStyle
{
    public function __construct(
        public string $gradient,
        public string $textColor,
        public float $hue1,
        public float $hue2,
    ) {}

    public function backgroundStyle(): string
    {
        return "background: {$this->gradient};";
    }

    public function textStyle(): string
    {
        return "color: {$this->textColor};";
    }

    public function toStyle(): string
    {
        return $this->backgroundStyle() . ' ' . $this->textStyle();
    }
}
```

### Avatar Blade Component

```php
<?php

namespace Daikazu\LaravelFrontdoor\View\Components;

use Daikazu\LaravelFrontdoor\Support\Avatar as AvatarHelper;
use Illuminate\View\Component;

class Avatar extends Component
{
    public string $initial;
    public string $gradient;
    public string $textColor;
    public string $sizeClasses;

    protected array $sizes = [
        'xs' => 'w-6 h-6 text-xs',
        'sm' => 'w-8 h-8 text-sm',
        'md' => 'w-10 h-10 text-base',
        'lg' => 'w-12 h-12 text-lg',
        'xl' => 'w-16 h-16 text-xl',
    ];

    public function __construct(
        public string $identifier,
        public ?string $name = null,
        public string $size = 'md',
    ) {
        $avatar = AvatarHelper::make($identifier, $name);

        $this->initial = $avatar->initial;
        $this->gradient = $avatar->style->gradient;
        $this->textColor = $avatar->style->textColor;
        $this->sizeClasses = $this->sizes[$size] ?? $this->sizes['md'];
    }

    public function render()
    {
        return view('frontdoor::components.avatar');
    }
}
```

```blade
{{-- resources/views/components/avatar.blade.php --}}

<div
    {{ $attributes->merge(['class' => "{$sizeClasses} rounded-full flex items-center justify-center font-semibold select-none"]) }}
    style="background: {{ $gradient }}; color: {{ $textColor }};"
    title="{{ $name ?? $identifier }}"
>
    {{ $initial }}
</div>
```

### Visual Consistency Guarantee

- Same email → same colors, every time, across all sessions
- SHA1 hash ensures deterministic output
- Minimum 30° hue separation prevents muddy gradients
- Configurable saturation/lightness for brand alignment

---

## Custom Provider Example

### HubSpot CRM Provider

```php
<?php

namespace App\Frontdoor\Providers;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\AccountProvider;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Support\Facades\Http;

class HubspotAccountProvider implements AccountProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://api.hubapi.com',
    ) {}

    public function findByEmail(string $email): ?AccountData
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/crm/v3/objects/contacts/search", [
                'filterGroups' => [[
                    'filters' => [[
                        'propertyName' => 'email',
                        'operator' => 'EQ',
                        'value' => $email,
                    ]],
                ]],
            ]);

        if (! $response->successful()) {
            return null;
        }

        $results = $response->json('results', []);

        if (empty($results)) {
            return null;
        }

        $contact = $results[0];
        $props = $contact['properties'] ?? [];

        return new SimpleAccountData(
            id: (string) $contact['id'],
            name: trim(($props['firstname'] ?? '') . ' ' . ($props['lastname'] ?? '')) ?: $email,
            email: $props['email'] ?? $email,
            phone: $props['phone'] ?? null,
            avatarUrl: null,
            metadata: [
                'hubspot_id' => $contact['id'],
                'company' => $props['company'] ?? null,
                'lifecycle_stage' => $props['lifecyclestage'] ?? null,
            ],
        );
    }

    public function exists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }
}
```

### Registration

```php
// In AppServiceProvider::boot()
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;

Frontdoor::accounts()->extend('hubspot', function ($app) {
    return new \App\Frontdoor\Providers\HubspotAccountProvider(
        apiKey: config('services.hubspot.api_key'),
    );
});
```

### Config

```php
// config/frontdoor.php
'accounts' => [
    'driver' => 'hubspot',
],
```

---

## Testing Plan

### Test Structure

```
tests/
├── Feature/
│   ├── OtpFlowTest.php
│   ├── AuthGuardTest.php
│   ├── LivewireLoginFlowTest.php
│   ├── BladeLoginFlowTest.php
│   ├── NavLoginComponentTest.php
│   └── LogoutTest.php
├── Unit/
│   ├── AccountManagerTest.php
│   ├── ConfigProviderTest.php
│   ├── OtpManagerTest.php
│   ├── AvatarTest.php
│   └── SimpleAccountDataTest.php
└── TestCase.php
```

### Core Test Cases

**OTP Flow:**
- Sends OTP email for valid account
- Rejects OTP request for unknown account
- Verifies correct OTP and establishes session
- Rejects incorrect OTP
- Rejects expired OTP
- Invalidates OTP after single use

**Rate Limiting:**
- Rate limits OTP requests per email
- Invalidates OTP after too many failed verification attempts

**Avatar Determinism:**
- Generates consistent gradient for same identifier
- Generates different gradients for different identifiers
- Normalizes email case for consistency
- Extracts initial from name (including Unicode)

**Auth Guard:**
- Works with `auth()` helper functions
- Works with `@auth` blade directive
- Clears session on logout

### Edge Cases

| Edge Case | Expected Behavior |
|-----------|-------------------|
| Email with + alias (jane+test@example.com) | Treated as distinct identity |
| Unicode email (用户@例子.测试) | Supported, normalized to lowercase |
| Empty name in provider | Fall back to email local part |
| Provider returns null | AccountNotFoundException thrown |
| Cache driver unavailable | Graceful error with clear message |
| Livewire not installed | Auto-fallback to Blade components |
| Concurrent OTP requests | Last OTP wins, previous invalidated |
| Session expires mid-flow | Redirect to start with message |

---

## Events

| Event | Payload | When |
|-------|---------|------|
| `OtpRequested` | `email` | OTP generated and queued for sending |
| `OtpVerified` | `email` | OTP successfully verified |
| `LoginSucceeded` | `identity` | Session established |
| `LoginFailed` | `email`, `reason` | OTP invalid/expired or account not found |
| `LogoutSucceeded` | `identity` | Session cleared |

---

## Publishable Assets

```bash
# Config
php artisan vendor:publish --tag=frontdoor-config

# Views (components + auth screens)
php artisan vendor:publish --tag=frontdoor-views

# Mail templates
php artisan vendor:publish --tag=frontdoor-mail

# Translations
php artisan vendor:publish --tag=frontdoor-lang

# Routes (for customization)
php artisan vendor:publish --tag=frontdoor-routes
```

---

## Implementation Sequence

1. **Core contracts and exceptions**
2. **SimpleAccountData and ConfigAccountProvider**
3. **AccountManager (driver manager)**
4. **OtpStore contract and CacheOtpStore**
5. **OtpManager**
6. **FrontdoorIdentity (implements Authenticatable)**
7. **FrontdoorGuard and FrontdoorUserProvider**
8. **Service provider (guard registration)**
9. **OtpMail and OtpMailer**
10. **Events**
11. **Avatar helper and components**
12. **Livewire LoginFlow component**
13. **Blade fallback components**
14. **NavLogin component**
15. **Routes and controllers**
16. **Tests**
