# Laravel Frontdoor Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a drop-in OTP authentication package with provider-based account data, session-based identity, and polished UI components.

**Architecture:** Session-only authentication using a custom guard. Account data fetched from configurable drivers. OTPs stored in cache with HMAC hashing. Livewire-first UI with Blade+Alpine fallback.

**Tech Stack:** Laravel 12, PHP 8.4+, Livewire 4, Alpine.js, Pest 4

**Working Directory:** `/Users/mikewall/Code/laravel-frontdoor/.worktrees/feature-frontdoor`

---

## Phase 1: Core Contracts & Data Objects

### Task 1.1: Create AccountData Contract

**Files:**
- Create: `src/Contracts/AccountData.php`
- Test: `tests/Unit/SimpleAccountDataTest.php`

**Step 1: Write the failing test**

```php
<?php

use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

it('implements AccountData contract', function () {
    $data = new SimpleAccountData(
        id: '123',
        name: 'Jane Doe',
        email: 'jane@example.com',
        phone: '+1-555-0100',
        avatarUrl: 'https://example.com/avatar.jpg',
        metadata: ['role' => 'admin'],
    );

    expect($data->getId())->toBe('123');
    expect($data->getName())->toBe('Jane Doe');
    expect($data->getEmail())->toBe('jane@example.com');
    expect($data->getPhone())->toBe('+1-555-0100');
    expect($data->getAvatarUrl())->toBe('https://example.com/avatar.jpg');
    expect($data->getMetadata())->toBe(['role' => 'admin']);
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/SimpleAccountDataTest.php -v`
Expected: FAIL with "class not found"

**Step 3: Create AccountData contract**

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

**Step 4: Create SimpleAccountData implementation**

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

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

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

**Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/SimpleAccountDataTest.php -v`
Expected: PASS

**Step 6: Add more test cases**

```php
it('extracts initial from name', function () {
    $data = new SimpleAccountData(id: '1', name: 'Jane Doe', email: 'jane@example.com');
    expect($data->getInitial())->toBe('J');
});

it('handles unicode names', function () {
    $data = new SimpleAccountData(id: '1', name: '李明', email: 'li@example.com');
    expect($data->getInitial())->toBe('李');
});

it('converts to array', function () {
    $data = new SimpleAccountData(
        id: '123',
        name: 'Jane Doe',
        email: 'jane@example.com',
    );

    expect($data->toArray())->toBe([
        'id' => '123',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => null,
        'avatar_url' => null,
        'metadata' => [],
    ]);
});

it('allows nullable phone and avatar', function () {
    $data = new SimpleAccountData(id: '1', name: 'Test', email: 'test@example.com');
    expect($data->getPhone())->toBeNull();
    expect($data->getAvatarUrl())->toBeNull();
});
```

**Step 7: Run all tests**

Run: `vendor/bin/pest tests/Unit/SimpleAccountDataTest.php -v`
Expected: All PASS

**Step 8: Commit**

```bash
git add src/Contracts/AccountData.php src/Support/SimpleAccountData.php tests/Unit/SimpleAccountDataTest.php
git commit -m "feat: add AccountData contract and SimpleAccountData implementation"
```

---

### Task 1.2: Create AccountProvider Contract

**Files:**
- Create: `src/Contracts/AccountProvider.php`

**Step 1: Create contract**

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

**Step 2: Commit**

```bash
git add src/Contracts/AccountProvider.php
git commit -m "feat: add AccountProvider contract"
```

---

### Task 1.3: Create OtpStore Contract

**Files:**
- Create: `src/Contracts/OtpStore.php`

**Step 1: Create contract**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Contracts;

interface OtpStore
{
    /**
     * Store a hashed OTP for an identifier.
     */
    public function store(string $identifier, string $hashedCode, int $ttl): void;

    /**
     * Retrieve the hashed OTP for an identifier.
     */
    public function get(string $identifier): ?string;

    /**
     * Delete the OTP after successful verification.
     */
    public function forget(string $identifier): void;

    /**
     * Check if identifier has a pending OTP.
     */
    public function has(string $identifier): bool;
}
```

**Step 2: Commit**

```bash
git add src/Contracts/OtpStore.php
git commit -m "feat: add OtpStore contract"
```

---

### Task 1.4: Create OtpMailable Contract

**Files:**
- Create: `src/Contracts/OtpMailable.php`

**Step 1: Create contract**

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

**Step 2: Commit**

```bash
git add src/Contracts/OtpMailable.php
git commit -m "feat: add OtpMailable contract"
```

---

### Task 1.5: Create Exceptions

**Files:**
- Create: `src/Exceptions/AccountNotFoundException.php`
- Create: `src/Exceptions/TooManyOtpRequestsException.php`
- Create: `src/Exceptions/TooManyVerificationAttemptsException.php`

**Step 1: Create AccountNotFoundException**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Exceptions;

use Exception;

class AccountNotFoundException extends Exception
{
    public function __construct(string $email)
    {
        parent::__construct("No account found for email: {$email}");
    }
}
```

**Step 2: Create TooManyOtpRequestsException**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Exceptions;

use Exception;

class TooManyOtpRequestsException extends Exception
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct("Too many OTP requests. Try again in {$retryAfterSeconds} seconds.");
    }
}
```

**Step 3: Create TooManyVerificationAttemptsException**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Exceptions;

use Exception;

class TooManyVerificationAttemptsException extends Exception
{
    public function __construct()
    {
        parent::__construct('Too many verification attempts. Please request a new code.');
    }
}
```

**Step 4: Commit**

```bash
git add src/Exceptions/
git commit -m "feat: add authentication exceptions"
```

---

### Task 1.6: Run PHPStan and Fix Issues

**Step 1: Run PHPStan**

Run: `composer analyse`
Expected: Pass or list issues to fix

**Step 2: Fix any issues**

**Step 3: Commit if fixes were made**

```bash
git add -A
git commit -m "fix: resolve PHPStan issues in contracts"
```

---

## Phase 2: Config Account Provider

### Task 2.1: Create ConfigAccountProvider

**Files:**
- Create: `src/Drivers/ConfigAccountProvider.php`
- Test: `tests/Unit/ConfigProviderTest.php`

**Step 1: Write the failing tests**

```php
<?php

use Daikazu\LaravelFrontdoor\Drivers\ConfigAccountProvider;

it('finds account by email', function () {
    $provider = new ConfigAccountProvider([
        'jane@example.com' => [
            'name' => 'Jane Doe',
            'phone' => '+1-555-0100',
        ],
    ]);

    $account = $provider->findByEmail('jane@example.com');

    expect($account)->not->toBeNull();
    expect($account->getName())->toBe('Jane Doe');
    expect($account->getEmail())->toBe('jane@example.com');
    expect($account->getPhone())->toBe('+1-555-0100');
});

it('returns null for unknown email', function () {
    $provider = new ConfigAccountProvider([]);
    expect($provider->findByEmail('unknown@example.com'))->toBeNull();
});

it('normalizes email to lowercase', function () {
    $provider = new ConfigAccountProvider([
        'jane@example.com' => ['name' => 'Jane'],
    ]);

    expect($provider->findByEmail('JANE@EXAMPLE.COM'))->not->toBeNull();
});

it('checks if email exists', function () {
    $provider = new ConfigAccountProvider([
        'jane@example.com' => ['name' => 'Jane'],
    ]);

    expect($provider->exists('jane@example.com'))->toBeTrue();
    expect($provider->exists('unknown@example.com'))->toBeFalse();
});

it('generates name from email if not provided', function () {
    $provider = new ConfigAccountProvider([
        'john.doe@example.com' => [],
    ]);

    $account = $provider->findByEmail('john.doe@example.com');
    expect($account->getName())->toBe('John Doe');
});

it('generates id from email hash if not provided', function () {
    $provider = new ConfigAccountProvider([
        'jane@example.com' => ['name' => 'Jane'],
    ]);

    $account = $provider->findByEmail('jane@example.com');
    expect($account->getId())->toBe(md5('jane@example.com'));
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/ConfigProviderTest.php -v`
Expected: FAIL

**Step 3: Implement ConfigAccountProvider**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Drivers;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\AccountProvider;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

class ConfigAccountProvider implements AccountProvider
{
    /**
     * @param  array<string, array<string, mixed>>  $users
     */
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

**Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/ConfigProviderTest.php -v`
Expected: All PASS

**Step 5: Commit**

```bash
git add src/Drivers/ConfigAccountProvider.php tests/Unit/ConfigProviderTest.php
git commit -m "feat: add ConfigAccountProvider driver"
```

---

### Task 2.2: Create AccountManager

**Files:**
- Create: `src/Support/AccountManager.php`
- Test: `tests/Unit/AccountManagerTest.php`

**Step 1: Write failing tests**

```php
<?php

use Daikazu\LaravelFrontdoor\Support\AccountManager;
use Daikazu\LaravelFrontdoor\Drivers\ConfigAccountProvider;

beforeEach(function () {
    config(['frontdoor.accounts.driver' => 'config']);
    config(['frontdoor.accounts.drivers.config.users' => [
        'jane@example.com' => ['name' => 'Jane Doe'],
    ]]);
});

it('creates config driver by default', function () {
    $manager = app(AccountManager::class);
    expect($manager->driver())->toBeInstanceOf(ConfigAccountProvider::class);
});

it('returns default driver name', function () {
    $manager = app(AccountManager::class);
    expect($manager->getDefaultDriver())->toBe('config');
});

it('allows extending with custom drivers', function () {
    $manager = app(AccountManager::class);

    $manager->extend('custom', fn () => new ConfigAccountProvider([
        'custom@example.com' => ['name' => 'Custom User'],
    ]));

    $driver = $manager->driver('custom');
    expect($driver->exists('custom@example.com'))->toBeTrue();
});
```

**Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/AccountManagerTest.php -v`
Expected: FAIL

**Step 3: Implement AccountManager**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Support;

use Daikazu\LaravelFrontdoor\Contracts\AccountProvider;
use Daikazu\LaravelFrontdoor\Drivers\ConfigAccountProvider;
use Illuminate\Support\Manager;

/**
 * @method AccountProvider driver(?string $driver = null)
 */
class AccountManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('frontdoor.accounts.driver', 'config');
    }

    protected function createConfigDriver(): AccountProvider
    {
        /** @var array<string, array<string, mixed>> $users */
        $users = $this->config->get('frontdoor.accounts.drivers.config.users', []);

        return new ConfigAccountProvider($users);
    }
}
```

**Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/AccountManagerTest.php -v`
Expected: All PASS

**Step 5: Commit**

```bash
git add src/Support/AccountManager.php tests/Unit/AccountManagerTest.php
git commit -m "feat: add AccountManager driver manager"
```

---

## Phase 3: OTP System

### Task 3.1: Create CacheOtpStore

**Files:**
- Create: `src/Otp/CacheOtpStore.php`
- Test: `tests/Unit/CacheOtpStoreTest.php`

**Step 1: Write failing tests**

```php
<?php

use Daikazu\LaravelFrontdoor\Otp\CacheOtpStore;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('stores hashed code with ttl', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');

    $store->store('identifier123', 'hashedcode', 600);

    expect(Cache::get('test:otp:identifier123'))->toBe('hashedcode');
});

it('retrieves stored code', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');
    $store->store('id', 'hash', 600);

    expect($store->get('id'))->toBe('hash');
});

it('returns null for missing code', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');
    expect($store->get('missing'))->toBeNull();
});

it('forgets stored code', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');
    $store->store('id', 'hash', 600);
    $store->forget('id');

    expect($store->get('id'))->toBeNull();
});

it('checks if code exists', function () {
    $store = new CacheOtpStore(Cache::store(), 'test:otp:');

    expect($store->has('id'))->toBeFalse();

    $store->store('id', 'hash', 600);

    expect($store->has('id'))->toBeTrue();
});
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Unit/CacheOtpStoreTest.php -v`
Expected: FAIL

**Step 3: Implement CacheOtpStore**

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
        return $this->prefix.$identifier;
    }
}
```

**Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/CacheOtpStoreTest.php -v`
Expected: All PASS

**Step 5: Commit**

```bash
git add src/Otp/CacheOtpStore.php tests/Unit/CacheOtpStoreTest.php
git commit -m "feat: add CacheOtpStore implementation"
```

---

### Task 3.2: Create OtpManager

**Files:**
- Create: `src/Otp/OtpManager.php`
- Test: `tests/Unit/OtpManagerTest.php`

**Step 1: Write failing tests**

```php
<?php

use Daikazu\LaravelFrontdoor\Otp\OtpManager;
use Daikazu\LaravelFrontdoor\Otp\CacheOtpStore;
use Daikazu\LaravelFrontdoor\Events\OtpRequested;
use Daikazu\LaravelFrontdoor\Events\OtpVerified;
use Daikazu\LaravelFrontdoor\Events\LoginFailed;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyOtpRequestsException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyVerificationAttemptsException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('frontdoor:rate:'.hash('sha256', 'test@example.com'));
    RateLimiter::clear('frontdoor:verify:'.hash('sha256', 'test@example.com'));
    Event::fake();
});

function createOtpManager(): OtpManager
{
    return new OtpManager(
        new CacheOtpStore(Cache::store(), 'frontdoor:otp:'),
        [
            'length' => 6,
            'ttl' => 600,
            'rate_limit' => [
                'max_attempts' => 5,
                'decay_seconds' => 300,
            ],
        ]
    );
}

it('generates 6-digit code', function () {
    $manager = createOtpManager();
    $code = $manager->generate('test@example.com');

    expect($code)->toHaveLength(6);
    expect($code)->toMatch('/^\d{6}$/');
});

it('fires OtpRequested event on generate', function () {
    $manager = createOtpManager();
    $manager->generate('test@example.com');

    Event::assertDispatched(OtpRequested::class, fn ($e) => $e->email === 'test@example.com');
});

it('verifies correct code', function () {
    $manager = createOtpManager();
    $code = $manager->generate('test@example.com');

    expect($manager->verify('test@example.com', $code))->toBeTrue();
});

it('fires OtpVerified event on successful verify', function () {
    $manager = createOtpManager();
    $code = $manager->generate('test@example.com');
    $manager->verify('test@example.com', $code);

    Event::assertDispatched(OtpVerified::class);
});

it('rejects incorrect code', function () {
    $manager = createOtpManager();
    $manager->generate('test@example.com');

    expect($manager->verify('test@example.com', '000000'))->toBeFalse();
});

it('fires LoginFailed event on incorrect code', function () {
    $manager = createOtpManager();
    $manager->generate('test@example.com');
    $manager->verify('test@example.com', '000000');

    Event::assertDispatched(LoginFailed::class, fn ($e) => $e->reason === 'invalid_code');
});

it('invalidates code after single use', function () {
    $manager = createOtpManager();
    $code = $manager->generate('test@example.com');

    $manager->verify('test@example.com', $code);

    expect($manager->verify('test@example.com', $code))->toBeFalse();
});

it('checks if OTP is pending', function () {
    $manager = createOtpManager();

    expect($manager->hasPending('test@example.com'))->toBeFalse();

    $manager->generate('test@example.com');

    expect($manager->hasPending('test@example.com'))->toBeTrue();
});

it('rate limits OTP generation', function () {
    $manager = createOtpManager();

    for ($i = 0; $i < 5; $i++) {
        $manager->generate('test@example.com');
    }

    expect(fn () => $manager->generate('test@example.com'))
        ->toThrow(TooManyOtpRequestsException::class);
});

it('throws after too many verification attempts', function () {
    $manager = createOtpManager();
    $manager->generate('test@example.com');

    for ($i = 0; $i < 5; $i++) {
        try {
            $manager->verify('test@example.com', '000000');
        } catch (TooManyVerificationAttemptsException $e) {
            expect($i)->toBe(4);
            return;
        }
    }

    $this->fail('Expected TooManyVerificationAttemptsException');
});
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Unit/OtpManagerTest.php -v`
Expected: FAIL

**Step 3: Create Events first**

Create `src/Events/OtpRequested.php`:

```php
<?php

namespace Daikazu\LaravelFrontdoor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OtpRequested
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
    ) {}
}
```

Create `src/Events/OtpVerified.php`:

```php
<?php

namespace Daikazu\LaravelFrontdoor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OtpVerified
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
    ) {}
}
```

Create `src/Events/LoginFailed.php`:

```php
<?php

namespace Daikazu\LaravelFrontdoor\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LoginFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $email,
        public readonly string $reason,
    ) {}
}
```

Create `src/Events/LoginSucceeded.php`:

```php
<?php

namespace Daikazu\LaravelFrontdoor\Events;

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Illuminate\Foundation\Events\Dispatchable;

class LoginSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly FrontdoorIdentity $identity,
    ) {}
}
```

Create `src/Events/LogoutSucceeded.php`:

```php
<?php

namespace Daikazu\LaravelFrontdoor\Events;

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Illuminate\Foundation\Events\Dispatchable;

class LogoutSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly FrontdoorIdentity $identity,
    ) {}
}
```

**Step 4: Implement OtpManager**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Otp;

use Daikazu\LaravelFrontdoor\Contracts\OtpStore;
use Daikazu\LaravelFrontdoor\Events\LoginFailed;
use Daikazu\LaravelFrontdoor\Events\OtpRequested;
use Daikazu\LaravelFrontdoor\Events\OtpVerified;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyOtpRequestsException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyVerificationAttemptsException;
use Illuminate\Support\Facades\RateLimiter;

class OtpManager
{
    /**
     * @param  array{length: int, ttl: int, rate_limit: array{max_attempts: int, decay_seconds: int}}  $config
     */
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

        if ($storedHash === null) {
            event(new LoginFailed($email, 'expired'));

            return false;
        }

        if (! $this->verifyHash($code, $storedHash)) {
            $this->incrementAttempts($email);
            event(new LoginFailed($email, 'invalid_code'));

            return false;
        }

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
        $key = 'frontdoor:rate:'.$this->identifier($email);
        $maxAttempts = $this->config['rate_limit']['max_attempts'];
        $decay = $this->config['rate_limit']['decay_seconds'];

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw new TooManyOtpRequestsException($seconds);
        }

        RateLimiter::hit($key, $decay);
    }

    protected function incrementAttempts(string $email): void
    {
        $key = 'frontdoor:verify:'.$this->identifier($email);
        RateLimiter::hit($key, 300);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->store->forget($this->identifier($email));

            throw new TooManyVerificationAttemptsException;
        }
    }

    protected function clearRateLimit(string $email): void
    {
        RateLimiter::clear('frontdoor:rate:'.$this->identifier($email));
        RateLimiter::clear('frontdoor:verify:'.$this->identifier($email));
    }
}
```

**Step 5: Run tests**

Run: `vendor/bin/pest tests/Unit/OtpManagerTest.php -v`
Expected: All PASS

**Step 6: Commit**

```bash
git add src/Events/ src/Otp/OtpManager.php tests/Unit/OtpManagerTest.php
git commit -m "feat: add OtpManager with rate limiting and events"
```

---

## Phase 4: Authentication System

### Task 4.1: Create FrontdoorIdentity

**Files:**
- Create: `src/Auth/FrontdoorIdentity.php`
- Test: `tests/Unit/FrontdoorIdentityTest.php`

**Step 1: Write failing tests**

```php
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
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Unit/FrontdoorIdentityTest.php -v`
Expected: FAIL

**Step 3: Implement FrontdoorIdentity**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Auth;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Illuminate\Contracts\Auth\Authenticatable;

class FrontdoorIdentity implements Authenticatable
{
    public function __construct(
        protected AccountData $account
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->account->getId();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Not supported - session-only auth
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getId(): string
    {
        return $this->account->getId();
    }

    public function getName(): string
    {
        return $this->account->getName();
    }

    public function getEmail(): string
    {
        return $this->account->getEmail();
    }

    public function getPhone(): ?string
    {
        return $this->account->getPhone();
    }

    public function getAvatarUrl(): ?string
    {
        return $this->account->getAvatarUrl();
    }

    public function getMetadata(): array
    {
        return $this->account->getMetadata();
    }

    public function getInitial(): string
    {
        return $this->account->getInitial();
    }

    public function toArray(): array
    {
        return $this->account->toArray();
    }

    public function getAccountData(): AccountData
    {
        return $this->account;
    }
}
```

**Step 4: Run tests**

Run: `vendor/bin/pest tests/Unit/FrontdoorIdentityTest.php -v`
Expected: All PASS

**Step 5: Commit**

```bash
git add src/Auth/FrontdoorIdentity.php tests/Unit/FrontdoorIdentityTest.php
git commit -m "feat: add FrontdoorIdentity implementing Authenticatable"
```

---

### Task 4.2: Create FrontdoorUserProvider

**Files:**
- Create: `src/Auth/FrontdoorUserProvider.php`

**Step 1: Implement FrontdoorUserProvider**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Auth;

use Daikazu\LaravelFrontdoor\Contracts\AccountProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class FrontdoorUserProvider implements UserProvider
{
    public function __construct(
        protected AccountProvider $accountProvider
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        return null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Not supported
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (! isset($credentials['email'])) {
            return null;
        }

        $account = $this->accountProvider->findByEmail($credentials['email']);

        if ($account === null) {
            return null;
        }

        return new FrontdoorIdentity($account);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Not supported - OTP auth
    }
}
```

**Step 2: Commit**

```bash
git add src/Auth/FrontdoorUserProvider.php
git commit -m "feat: add FrontdoorUserProvider"
```

---

### Task 4.3: Create FrontdoorGuard

**Files:**
- Create: `src/Auth/FrontdoorGuard.php`
- Test: `tests/Feature/AuthGuardTest.php`

**Step 1: Write failing tests**

```php
<?php

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    session()->flush();
});

it('checks if user is authenticated', function () {
    expect(Auth::guard('frontdoor')->check())->toBeFalse();
});

it('returns null user when not authenticated', function () {
    expect(Auth::guard('frontdoor')->user())->toBeNull();
});

it('returns null id when not authenticated', function () {
    expect(Auth::guard('frontdoor')->id())->toBeNull();
});

it('logs in user via identity', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);

    expect(Auth::guard('frontdoor')->check())->toBeTrue();
    expect(Auth::guard('frontdoor')->user())->toBeInstanceOf(FrontdoorIdentity::class);
    expect(Auth::guard('frontdoor')->id())->toBe('123');
});

it('persists identity across requests via session', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);

    // Simulate new request by clearing the guard's cached user
    Auth::guard('frontdoor')->setUser(null);

    // User should still be authenticated from session
    expect(Auth::guard('frontdoor')->check())->toBeTrue();
});

it('logs out user', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);
    Auth::guard('frontdoor')->logout();

    expect(Auth::guard('frontdoor')->check())->toBeFalse();
    expect(Auth::guard('frontdoor')->user())->toBeNull();
});

it('works with auth helper', function () {
    $account = new SimpleAccountData(id: '123', name: 'Jane', email: 'jane@example.com');
    $identity = new FrontdoorIdentity($account);

    Auth::guard('frontdoor')->login($identity);

    expect(auth('frontdoor')->check())->toBeTrue();
    expect(auth('frontdoor')->user()->getName())->toBe('Jane');
    expect(auth('frontdoor')->id())->toBe('123');
});
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Feature/AuthGuardTest.php -v`
Expected: FAIL

**Step 3: Implement FrontdoorGuard**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Auth;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Events\LoginSucceeded;
use Daikazu\LaravelFrontdoor\Events\LogoutSucceeded;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;

class FrontdoorGuard implements Guard
{
    protected ?FrontdoorIdentity $user = null;

    protected bool $loggedOut = false;

    public function __construct(
        protected Session $session,
        protected string $sessionKey = 'frontdoor_identity',
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->loggedOut) {
            return null;
        }

        if ($this->user !== null) {
            return $this->user;
        }

        $data = $this->session->get($this->sessionKey);

        if ($data === null || ! is_array($data)) {
            return null;
        }

        $account = $this->hydrateAccountData($data);
        $this->user = new FrontdoorIdentity($account);

        return $this->user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable|null $user): static
    {
        if ($user === null) {
            $this->user = null;

            return $this;
        }

        if (! $user instanceof FrontdoorIdentity) {
            throw new \InvalidArgumentException('User must be an instance of FrontdoorIdentity');
        }

        $this->user = $user;

        return $this;
    }

    public function login(FrontdoorIdentity $identity): void
    {
        $this->user = $identity;
        $this->loggedOut = false;

        $this->session->put($this->sessionKey, $identity->toArray());
        $this->session->regenerate();

        event(new LoginSucceeded($identity));
    }

    public function logout(): void
    {
        $user = $this->user;

        $this->user = null;
        $this->loggedOut = true;

        $this->session->forget($this->sessionKey);
        $this->session->invalidate();
        $this->session->regenerateToken();

        if ($user instanceof FrontdoorIdentity) {
            event(new LogoutSucceeded($user));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function hydrateAccountData(array $data): AccountData
    {
        return new SimpleAccountData(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            email: $data['email'] ?? '',
            phone: $data['phone'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
```

**Step 4: Run tests**

Run: `vendor/bin/pest tests/Feature/AuthGuardTest.php -v`
Expected: Will still fail until service provider registers guard

**Step 5: Commit**

```bash
git add src/Auth/FrontdoorGuard.php tests/Feature/AuthGuardTest.php
git commit -m "feat: add FrontdoorGuard implementing Laravel Guard"
```

---

## Phase 5: Configuration & Service Provider

### Task 5.1: Update Config File

**Files:**
- Modify: `config/frontdoor.php`

**Step 1: Update config**

```php
<?php

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
        'ttl' => 600,
        'cache_store' => null,
        'cache_prefix' => 'frontdoor:otp:',

        'rate_limit' => [
            'max_attempts' => 5,
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
        'mode' => 'modal',
        'prefer_livewire' => true,
        'login_route' => '/login',

        'nav' => [
            'login_label' => 'Login',
            'account_label' => 'Account',
            'logout_label' => 'Logout',
            'account_route' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Avatar Settings
    |--------------------------------------------------------------------------
    */
    'avatar' => [
        'algorithm' => 'gradient',
        'saturation' => 65,
        'lightness' => 55,
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

**Step 2: Commit**

```bash
git add config/frontdoor.php
git commit -m "feat: add complete package configuration"
```

---

### Task 5.2: Update Service Provider

**Files:**
- Modify: `src/LaravelFrontdoorServiceProvider.php`

**Step 1: Update service provider**

```php
<?php

namespace Daikazu\LaravelFrontdoor;

use Daikazu\LaravelFrontdoor\Auth\FrontdoorGuard;
use Daikazu\LaravelFrontdoor\Auth\FrontdoorUserProvider;
use Daikazu\LaravelFrontdoor\Contracts\OtpStore;
use Daikazu\LaravelFrontdoor\Otp\CacheOtpStore;
use Daikazu\LaravelFrontdoor\Otp\OtpManager;
use Daikazu\LaravelFrontdoor\Support\AccountManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelFrontdoorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-frontdoor')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(AccountManager::class, function ($app) {
            return new AccountManager($app);
        });

        $this->app->singleton(OtpStore::class, function ($app) {
            $store = config('frontdoor.otp.cache_store');
            $prefix = config('frontdoor.otp.cache_prefix', 'frontdoor:otp:');

            return new CacheOtpStore(
                Cache::store($store),
                $prefix
            );
        });

        $this->app->singleton(OtpManager::class, function ($app) {
            return new OtpManager(
                $app->make(OtpStore::class),
                config('frontdoor.otp')
            );
        });

        $this->app->singleton(Frontdoor::class, function ($app) {
            return new Frontdoor(
                $app->make(AccountManager::class),
                $app->make(OtpManager::class)
            );
        });

        $this->app->alias(Frontdoor::class, 'frontdoor');
    }

    public function packageBooted(): void
    {
        $this->registerAuthGuard();
    }

    protected function registerAuthGuard(): void
    {
        Auth::provider('frontdoor', function ($app, array $config) {
            return new FrontdoorUserProvider(
                $app->make(AccountManager::class)->driver()
            );
        });

        Auth::extend('frontdoor', function ($app, $name, array $config) {
            return new FrontdoorGuard(
                $app['session.store'],
                'frontdoor_identity'
            );
        });

        config([
            'auth.guards.frontdoor' => [
                'driver' => 'frontdoor',
                'provider' => 'frontdoor',
            ],
            'auth.providers.frontdoor' => [
                'driver' => 'frontdoor',
            ],
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add src/LaravelFrontdoorServiceProvider.php
git commit -m "feat: register auth guard, providers, and singletons"
```

---

### Task 5.3: Create Main Frontdoor Service

**Files:**
- Modify: `src/Frontdoor.php`

**Step 1: Implement Frontdoor service**

```php
<?php

namespace Daikazu\LaravelFrontdoor;

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Daikazu\LaravelFrontdoor\Exceptions\AccountNotFoundException;
use Daikazu\LaravelFrontdoor\Otp\OtpManager;
use Daikazu\LaravelFrontdoor\Support\AccountManager;
use Illuminate\Support\Facades\Auth;

class Frontdoor
{
    public function __construct(
        protected AccountManager $accountManager,
        protected OtpManager $otpManager,
    ) {}

    public function accounts(): AccountManager
    {
        return $this->accountManager;
    }

    public function otp(): OtpManager
    {
        return $this->otpManager;
    }

    public function requestOtp(string $email): string
    {
        $account = $this->accountManager->driver()->findByEmail($email);

        if ($account === null) {
            throw new AccountNotFoundException($email);
        }

        return $this->otpManager->generate($email);
    }

    public function verify(string $email, string $code): bool
    {
        if (! $this->otpManager->verify($email, $code)) {
            return false;
        }

        $account = $this->accountManager->driver()->findByEmail($email);

        if ($account === null) {
            return false;
        }

        $identity = new FrontdoorIdentity($account);

        /** @var \Daikazu\LaravelFrontdoor\Auth\FrontdoorGuard $guard */
        $guard = Auth::guard('frontdoor');
        $guard->login($identity);

        return true;
    }

    public function loginAs(string $email): bool
    {
        $account = $this->accountManager->driver()->findByEmail($email);

        if ($account === null) {
            return false;
        }

        $identity = new FrontdoorIdentity($account);

        /** @var \Daikazu\LaravelFrontdoor\Auth\FrontdoorGuard $guard */
        $guard = Auth::guard('frontdoor');
        $guard->login($identity);

        return true;
    }
}
```

**Step 2: Commit**

```bash
git add src/Frontdoor.php
git commit -m "feat: add main Frontdoor service class"
```

---

### Task 5.4: Update Facade

**Files:**
- Modify: `src/Facades/LaravelFrontdoor.php`

**Step 1: Rename and update facade**

Rename file to `src/Facades/Frontdoor.php`:

```php
<?php

namespace Daikazu\LaravelFrontdoor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Daikazu\LaravelFrontdoor\Support\AccountManager accounts()
 * @method static \Daikazu\LaravelFrontdoor\Otp\OtpManager otp()
 * @method static string requestOtp(string $email)
 * @method static bool verify(string $email, string $code)
 * @method static bool loginAs(string $email)
 *
 * @see \Daikazu\LaravelFrontdoor\Frontdoor
 */
class Frontdoor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Daikazu\LaravelFrontdoor\Frontdoor::class;
    }
}
```

**Step 2: Delete old facade file**

```bash
rm src/Facades/LaravelFrontdoor.php
```

**Step 3: Update composer.json alias**

Update the alias in `composer.json`:

```json
"aliases": {
    "Frontdoor": "Daikazu\\LaravelFrontdoor\\Facades\\Frontdoor"
}
```

**Step 4: Commit**

```bash
git add src/Facades/ composer.json
git rm src/Facades/LaravelFrontdoor.php 2>/dev/null || true
git commit -m "refactor: rename facade to Frontdoor"
```

---

### Task 5.5: Run Auth Guard Tests

**Step 1: Run tests**

Run: `vendor/bin/pest tests/Feature/AuthGuardTest.php -v`
Expected: All PASS

**Step 2: Run all tests**

Run: `vendor/bin/pest -v`
Expected: All PASS

**Step 3: Run PHPStan**

Run: `composer analyse`
Expected: Pass

**Step 4: Commit any fixes**

```bash
git add -A
git commit -m "fix: resolve test and static analysis issues"
```

---

## Phase 6: Avatar System

### Task 6.1: Create Avatar Helper Classes

**Files:**
- Create: `src/Support/AvatarStyle.php`
- Create: `src/Support/AvatarData.php`
- Create: `src/Support/Avatar.php`
- Test: `tests/Unit/AvatarTest.php`

**Step 1: Write failing tests**

```php
<?php

use Daikazu\LaravelFrontdoor\Support\Avatar;

it('generates consistent gradient for same identifier', function () {
    $style1 = Avatar::gradient('jane@example.com');
    $style2 = Avatar::gradient('jane@example.com');

    expect($style1->gradient)->toBe($style2->gradient);
    expect($style1->textColor)->toBe($style2->textColor);
});

it('generates different gradients for different identifiers', function () {
    $style1 = Avatar::gradient('jane@example.com');
    $style2 = Avatar::gradient('bob@example.com');

    expect($style1->gradient)->not->toBe($style2->gradient);
});

it('normalizes email case for consistency', function () {
    $style1 = Avatar::gradient('Jane@Example.com');
    $style2 = Avatar::gradient('jane@example.com');

    expect($style1->gradient)->toBe($style2->gradient);
});

it('extracts initial from name', function () {
    expect(Avatar::initial('Jane Doe'))->toBe('J');
    expect(Avatar::initial('bob'))->toBe('B');
});

it('extracts initial from email', function () {
    expect(Avatar::initial('jane@example.com'))->toBe('J');
});

it('handles unicode names', function () {
    expect(Avatar::initial('李明'))->toBe('李');
});

it('makes complete avatar data', function () {
    $avatar = Avatar::make('jane@example.com', 'Jane Doe');

    expect($avatar->initial)->toBe('J');
    expect($avatar->identifier)->toBe('jane@example.com');
    expect($avatar->style->gradient)->toContain('linear-gradient');
});

it('generates valid CSS gradient string', function () {
    $style = Avatar::gradient('test@example.com');

    expect($style->gradient)->toContain('linear-gradient');
    expect($style->gradient)->toContain('hsl');
    expect($style->gradient)->toContain('deg');
});

it('returns proper text color for contrast', function () {
    $style = Avatar::gradient('test@example.com');

    expect($style->textColor)->toBeIn(['#1f2937', '#ffffff']);
});
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Unit/AvatarTest.php -v`
Expected: FAIL

**Step 3: Create AvatarStyle**

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
        return $this->backgroundStyle().' '.$this->textStyle();
    }
}
```

**Step 4: Create AvatarData**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Support;

readonly class AvatarData
{
    public function __construct(
        public string $initial,
        public AvatarStyle $style,
        public string $identifier,
    ) {}
}
```

**Step 5: Create Avatar**

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

        if (abs($hue1 - $hue2) < 30) {
            $hue2 = fmod($hue1 + 60, 360);
        }

        $color1 = sprintf('hsl(%d, %d%%, %d%%)', (int) $hue1, $saturation, $lightness);
        $color2 = sprintf('hsl(%d, %d%%, %d%%)', (int) $hue2, $saturation, $lightness);

        $textColor = $lightness > 55 ? '#1f2937' : '#ffffff';

        return new AvatarStyle(
            gradient: sprintf('linear-gradient(%ddeg, %s, %s)', (int) $angle, $color1, $color2),
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

**Step 6: Run tests**

Run: `vendor/bin/pest tests/Unit/AvatarTest.php -v`
Expected: All PASS

**Step 7: Commit**

```bash
git add src/Support/AvatarStyle.php src/Support/AvatarData.php src/Support/Avatar.php tests/Unit/AvatarTest.php
git commit -m "feat: add deterministic avatar gradient system"
```

---

## Phase 7: Mail System

### Task 7.1: Create OtpMail Mailable

**Files:**
- Create: `src/Mail/OtpMail.php`
- Create: `resources/views/mail/otp.blade.php`

**Step 1: Create OtpMail**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Mail;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\OtpMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements OtpMailable
{
    use Queueable, SerializesModels;

    public string $code = '';

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

    protected function fromAddress(): Address
    {
        return new Address(
            config('frontdoor.mail.from.address') ?? config('mail.from.address'),
            config('frontdoor.mail.from.name') ?? config('mail.from.name'),
        );
    }
}
```

**Step 2: Create mail view directory**

```bash
mkdir -p resources/views/mail
```

**Step 3: Create email template**

Create `resources/views/mail/otp.blade.php`:

```blade
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

**Step 4: Commit**

```bash
git add src/Mail/OtpMail.php resources/views/mail/
git commit -m "feat: add OtpMail mailable and email template"
```

---

### Task 7.2: Create OtpMailer Service

**Files:**
- Create: `src/Support/OtpMailer.php`

**Step 1: Create OtpMailer**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Support;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\OtpMailable;
use Illuminate\Support\Facades\Mail;

class OtpMailer
{
    public function send(string $email, string $code, ?AccountData $account = null): void
    {
        /** @var class-string<OtpMailable> $mailableClass */
        $mailableClass = config('frontdoor.mail.mailable');
        $ttlMinutes = (int) ceil(config('frontdoor.otp.ttl') / 60);

        /** @var OtpMailable $mailable */
        $mailable = app($mailableClass)
            ->setCode($code)
            ->setExpiresInMinutes($ttlMinutes);

        if ($account !== null) {
            $mailable->setAccount($account);
        }

        Mail::to($email)->send($mailable);
    }
}
```

**Step 2: Commit**

```bash
git add src/Support/OtpMailer.php
git commit -m "feat: add OtpMailer service"
```

---

## Phase 8: Run Full Test Suite

### Task 8.1: Create OTP Flow Feature Test

**Files:**
- Create: `tests/Feature/OtpFlowTest.php`

**Step 1: Write tests**

```php
<?php

use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Daikazu\LaravelFrontdoor\Exceptions\AccountNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    Mail::fake();

    config(['frontdoor.accounts.drivers.config.users' => [
        'jane@example.com' => ['name' => 'Jane Doe'],
    ]]);
});

it('sends OTP email for valid account', function () {
    Frontdoor::requestOtp('jane@example.com');

    Mail::assertSent(\Daikazu\LaravelFrontdoor\Mail\OtpMail::class, function ($mail) {
        return $mail->hasTo('jane@example.com');
    });
});

it('throws exception for unknown account', function () {
    expect(fn () => Frontdoor::requestOtp('unknown@example.com'))
        ->toThrow(AccountNotFoundException::class);
});

it('verifies correct OTP and logs in user', function () {
    $code = Frontdoor::otp()->generate('jane@example.com');

    $result = Frontdoor::verify('jane@example.com', $code);

    expect($result)->toBeTrue();
    expect(auth('frontdoor')->check())->toBeTrue();
    expect(auth('frontdoor')->user()->getEmail())->toBe('jane@example.com');
});

it('rejects incorrect OTP', function () {
    Frontdoor::otp()->generate('jane@example.com');

    $result = Frontdoor::verify('jane@example.com', '000000');

    expect($result)->toBeFalse();
    expect(auth('frontdoor')->check())->toBeFalse();
});
```

**Step 2: Run test**

Run: `vendor/bin/pest tests/Feature/OtpFlowTest.php -v`
Expected: All PASS

**Step 3: Commit**

```bash
git add tests/Feature/OtpFlowTest.php
git commit -m "test: add OTP flow feature tests"
```

---

### Task 8.2: Run Complete Test Suite

**Step 1: Run all tests**

Run: `vendor/bin/pest -v`
Expected: All PASS

**Step 2: Run PHPStan**

Run: `composer analyse`
Expected: Pass

**Step 3: Run Pint**

Run: `composer format`

**Step 4: Commit any fixes**

```bash
git add -A
git commit -m "style: apply code formatting"
```

---

## Phase 9: Blade Components

### Task 9.1: Create Avatar Blade Component

**Files:**
- Create: `src/View/Components/Avatar.php`
- Create: `resources/views/components/avatar.blade.php`

**Step 1: Create component class**

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

    /** @var array<string, string> */
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

**Step 2: Create component directory**

```bash
mkdir -p resources/views/components
```

**Step 3: Create blade view**

Create `resources/views/components/avatar.blade.php`:

```blade
<div
    {{ $attributes->merge(['class' => "{$sizeClasses} rounded-full flex items-center justify-center font-semibold select-none"]) }}
    style="background: {{ $gradient }}; color: {{ $textColor }};"
    title="{{ $name ?? $identifier }}"
>
    {{ $initial }}
</div>
```

**Step 4: Register component in service provider**

Add to `packageBooted()` in service provider:

```php
$this->loadViewComponentsAs('frontdoor', [
    \Daikazu\LaravelFrontdoor\View\Components\Avatar::class,
]);
```

**Step 5: Commit**

```bash
git add src/View/Components/Avatar.php resources/views/components/avatar.blade.php src/LaravelFrontdoorServiceProvider.php
git commit -m "feat: add Avatar blade component"
```

---

### Task 9.2: Create Modal Blade Component

**Files:**
- Create: `resources/views/components/modal.blade.php`

**Step 1: Create modal component**

Create `resources/views/components/modal.blade.php`:

```blade
<div
    x-data="{ open: false }"
    x-on:frontdoor-open.window="open = true"
    x-on:frontdoor-close.window="open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        x-on:click="open = false"
    ></div>

    {{-- Modal panel --}}
    <div class="flex min-h-full items-center justify-center p-4">
        <div
            x-show="open"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-6"
        >
            {{-- Close button --}}
            <div class="absolute right-0 top-0 pr-4 pt-4">
                <button
                    type="button"
                    x-on:click="open = false"
                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Content --}}
            @if(class_exists(\Livewire\Livewire::class) && config('frontdoor.ui.prefer_livewire', true))
                <livewire:frontdoor::login-flow />
            @else
                @include('frontdoor::blade.login-modal')
            @endif
        </div>
    </div>
</div>
```

**Step 2: Commit**

```bash
git add resources/views/components/modal.blade.php
git commit -m "feat: add modal blade component with Livewire detection"
```

---

## Phase 10: Livewire Components

### Task 10.1: Add Livewire Dependency

**Step 1: Add Livewire to composer.json require**

Add to `require` section:

```json
"livewire/livewire": "^3.0"
```

**Step 2: Run composer update**

Run: `composer update`

**Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add livewire dependency"
```

---

### Task 10.2: Create LoginFlow Livewire Component

**Files:**
- Create: `src/Livewire/LoginFlow.php`
- Create: `resources/views/livewire/login-flow.blade.php`

**Step 1: Create Livewire component**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Livewire;

use Daikazu\LaravelFrontdoor\Exceptions\AccountNotFoundException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyOtpRequestsException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyVerificationAttemptsException;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Daikazu\LaravelFrontdoor\Support\OtpMailer;
use Livewire\Attributes\Validate;
use Livewire\Component;

class LoginFlow extends Component
{
    public string $step = 'email';

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|digits:6')]
    public string $code = '';

    public ?string $errorMessage = null;

    public ?int $resendCountdown = null;

    public function submitEmail(): void
    {
        $this->validate(['email' => 'required|email']);
        $this->errorMessage = null;

        try {
            $otpCode = Frontdoor::requestOtp($this->email);

            $account = Frontdoor::accounts()->driver()->findByEmail($this->email);

            app(OtpMailer::class)->send($this->email, $otpCode, $account);

            $this->step = 'otp';
            $this->resendCountdown = 60;
        } catch (AccountNotFoundException $e) {
            $this->errorMessage = 'No account found with this email address.';
        } catch (TooManyOtpRequestsException $e) {
            $this->errorMessage = "Too many attempts. Please wait {$e->retryAfterSeconds} seconds.";
        }
    }

    public function submitCode(): void
    {
        $this->validate(['code' => 'required|digits:6']);
        $this->errorMessage = null;

        try {
            if (Frontdoor::verify($this->email, $this->code)) {
                $this->step = 'success';
                $this->dispatch('frontdoor-authenticated');
            } else {
                $this->errorMessage = 'Invalid or expired code. Please try again.';
            }
        } catch (TooManyVerificationAttemptsException $e) {
            $this->errorMessage = 'Too many attempts. Please request a new code.';
            $this->step = 'email';
            $this->code = '';
        }
    }

    public function resendCode(): void
    {
        $this->code = '';
        $this->submitEmail();
    }

    public function goBack(): void
    {
        $this->step = 'email';
        $this->code = '';
        $this->errorMessage = null;
    }

    public function closeModal(): void
    {
        $this->dispatch('frontdoor-close');
        $this->reset();
    }

    public function render()
    {
        return view('frontdoor::livewire.login-flow');
    }
}
```

**Step 2: Create views directory**

```bash
mkdir -p resources/views/livewire
```

**Step 3: Create blade view**

Create `resources/views/livewire/login-flow.blade.php`:

```blade
<div class="space-y-6">
    @if($step === 'email')
        <div class="text-center">
            <h3 class="text-lg font-semibold text-gray-900">Sign in to your account</h3>
            <p class="mt-2 text-sm text-gray-600">Enter your email to receive a login code</p>
        </div>

        <form wire:submit="submitEmail" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input
                    wire:model="email"
                    type="email"
                    id="email"
                    autocomplete="email"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-300 @enderror"
                    placeholder="you@example.com"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if($errorMessage)
                <div class="rounded-md bg-red-50 p-4">
                    <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                </div>
            @endif

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                <span wire:loading.remove>Continue</span>
                <span wire:loading>Sending...</span>
            </button>
        </form>

    @elseif($step === 'otp')
        <div class="text-center">
            <h3 class="text-lg font-semibold text-gray-900">Enter your code</h3>
            <p class="mt-2 text-sm text-gray-600">We sent a 6-digit code to <strong>{{ $email }}</strong></p>
        </div>

        <form wire:submit="submitCode" class="space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700">Verification code</label>
                <input
                    wire:model="code"
                    type="text"
                    id="code"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    autocomplete="one-time-code"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-center text-2xl tracking-widest @error('code') border-red-300 @enderror"
                    placeholder="000000"
                >
                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if($errorMessage)
                <div class="rounded-md bg-red-50 p-4">
                    <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                </div>
            @endif

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                <span wire:loading.remove>Verify</span>
                <span wire:loading>Verifying...</span>
            </button>
        </form>

        <div class="flex items-center justify-between text-sm">
            <button
                wire:click="goBack"
                type="button"
                class="text-indigo-600 hover:text-indigo-500"
            >
                &larr; Use different email
            </button>
            <button
                wire:click="resendCode"
                type="button"
                class="text-indigo-600 hover:text-indigo-500"
            >
                Resend code
            </button>
        </div>

    @elseif($step === 'success')
        <div class="text-center space-y-4">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">You're signed in!</h3>
            <p class="text-sm text-gray-600">Welcome back, {{ auth('frontdoor')->user()?->getName() }}</p>
            <button
                wire:click="closeModal"
                type="button"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                Continue
            </button>
        </div>
    @endif
</div>
```

**Step 4: Register Livewire component in service provider**

Add to `packageBooted()`:

```php
if (class_exists(\Livewire\Livewire::class)) {
    \Livewire\Livewire::component('frontdoor::login-flow', \Daikazu\LaravelFrontdoor\Livewire\LoginFlow::class);
}
```

**Step 5: Commit**

```bash
git add src/Livewire/LoginFlow.php resources/views/livewire/ src/LaravelFrontdoorServiceProvider.php
git commit -m "feat: add LoginFlow Livewire component"
```

---

### Task 10.3: Create NavLogin Component

**Files:**
- Create: `src/View/Components/NavLogin.php`
- Create: `resources/views/components/nav-login.blade.php`

**Step 1: Create component class**

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

**Step 2: Create blade view**

Create `resources/views/components/nav-login.blade.php`:

```blade
@auth('frontdoor')
    @php $identity = auth('frontdoor')->user(); @endphp

    <div
        x-data="{ open: false }"
        x-on:click.outside="open = false"
        class="relative"
    >
        <button
            x-on:click="open = !open"
            type="button"
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

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            x-cloak
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
        type="button"
        {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition']) }}
    >
        {{ $label }}
    </button>

    <x-frontdoor::modal />
@endauth
```

**Step 3: Register component in service provider**

Update the `loadViewComponentsAs` call:

```php
$this->loadViewComponentsAs('frontdoor', [
    \Daikazu\LaravelFrontdoor\View\Components\Avatar::class,
    \Daikazu\LaravelFrontdoor\View\Components\NavLogin::class,
]);
```

**Step 4: Commit**

```bash
git add src/View/Components/NavLogin.php resources/views/components/nav-login.blade.php src/LaravelFrontdoorServiceProvider.php
git commit -m "feat: add NavLogin component with dropdown"
```

---

## Phase 11: Routes & Controllers

### Task 11.1: Create Routes

**Files:**
- Create: `routes/frontdoor.php`

**Step 1: Create routes file**

```php
<?php

use Daikazu\LaravelFrontdoor\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('frontdoor.routes.middleware', ['web']))
    ->prefix(config('frontdoor.routes.prefix', 'frontdoor'))
    ->name('frontdoor.')
    ->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Blade fallback routes (when Livewire not available)
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'sendOtp'])->name('send-otp');
        Route::get('/verify', [AuthController::class, 'showVerify'])->name('verify');
        Route::post('/verify', [AuthController::class, 'verifyOtp'])->name('verify-otp');
    });
```

**Step 2: Register routes in service provider**

Add to `packageBooted()`:

```php
if (config('frontdoor.routes.enabled', true)) {
    $this->loadRoutesFrom(__DIR__.'/../routes/frontdoor.php');
}
```

**Step 3: Commit**

```bash
git add routes/frontdoor.php src/LaravelFrontdoorServiceProvider.php
git commit -m "feat: add package routes"
```

---

### Task 11.2: Create AuthController

**Files:**
- Create: `src/Http/Controllers/AuthController.php`

**Step 1: Create controller**

```php
<?php

namespace Daikazu\LaravelFrontdoor\Http\Controllers;

use Daikazu\LaravelFrontdoor\Exceptions\AccountNotFoundException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyOtpRequestsException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyVerificationAttemptsException;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Daikazu\LaravelFrontdoor\Support\OtpMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('frontdoor::blade.login');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        try {
            $email = $request->input('email');
            $code = Frontdoor::requestOtp($email);
            $account = Frontdoor::accounts()->driver()->findByEmail($email);

            app(OtpMailer::class)->send($email, $code, $account);

            session(['frontdoor_email' => $email]);

            return redirect()->route('frontdoor.verify');
        } catch (AccountNotFoundException $e) {
            return back()->withErrors(['email' => 'No account found with this email address.']);
        } catch (TooManyOtpRequestsException $e) {
            return back()->withErrors(['email' => "Too many attempts. Please wait {$e->retryAfterSeconds} seconds."]);
        }
    }

    public function showVerify(): View|RedirectResponse
    {
        if (! session()->has('frontdoor_email')) {
            return redirect()->route('frontdoor.login');
        }

        return view('frontdoor::blade.verify', [
            'email' => session('frontdoor_email'),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|digits:6']);

        $email = session('frontdoor_email');

        if (! $email) {
            return redirect()->route('frontdoor.login');
        }

        try {
            if (Frontdoor::verify($email, $request->input('code'))) {
                session()->forget('frontdoor_email');

                return redirect()->intended('/');
            }

            return back()->withErrors(['code' => 'Invalid or expired code.']);
        } catch (TooManyVerificationAttemptsException $e) {
            session()->forget('frontdoor_email');

            return redirect()->route('frontdoor.login')
                ->withErrors(['email' => 'Too many attempts. Please request a new code.']);
        }
    }

    public function logout(): RedirectResponse
    {
        /** @var \Daikazu\LaravelFrontdoor\Auth\FrontdoorGuard $guard */
        $guard = Auth::guard('frontdoor');
        $guard->logout();

        return redirect('/');
    }
}
```

**Step 2: Commit**

```bash
git add src/Http/Controllers/AuthController.php
git commit -m "feat: add AuthController for blade fallback"
```

---

### Task 11.3: Create Blade Fallback Views

**Files:**
- Create: `resources/views/blade/login.blade.php`
- Create: `resources/views/blade/verify.blade.php`
- Create: `resources/views/blade/login-modal.blade.php`

**Step 1: Create blade views directory**

```bash
mkdir -p resources/views/blade
```

**Step 2: Create login view**

Create `resources/views/blade/login.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Sign in</h1>
                <p class="mt-2 text-sm text-gray-600">Enter your email to receive a login code</p>
            </div>

            <form method="POST" action="{{ route('frontdoor.send-otp') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-300 @enderror"
                    >
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Continue
                </button>
            </form>
        </div>
    </div>
</body>
</html>
```

**Step 3: Create verify view**

Create `resources/views/blade/verify.blade.php`:

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Code - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Enter your code</h1>
                <p class="mt-2 text-sm text-gray-600">We sent a 6-digit code to <strong>{{ $email }}</strong></p>
            </div>

            <form method="POST" action="{{ route('frontdoor.verify-otp') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Verification code</label>
                    <input
                        type="text"
                        name="code"
                        id="code"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        autocomplete="one-time-code"
                        required
                        autofocus
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center text-2xl tracking-widest @error('code') border-red-300 @enderror"
                        placeholder="000000"
                    >
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Verify
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="{{ route('frontdoor.login') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                    &larr; Use different email
                </a>
            </div>
        </div>
    </div>
</body>
</html>
```

**Step 4: Create login-modal fallback**

Create `resources/views/blade/login-modal.blade.php`:

```blade
<div class="space-y-6">
    <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-900">Sign in to your account</h3>
        <p class="mt-2 text-sm text-gray-600">Enter your email to receive a login code</p>
    </div>

    <form method="POST" action="{{ route('frontdoor.send-otp') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_redirect" value="{{ url()->current() }}">

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
            <input
                type="email"
                name="email"
                id="email"
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="you@example.com"
            >
        </div>

        <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
            Continue
        </button>
    </form>
</div>
```

**Step 5: Commit**

```bash
git add resources/views/blade/
git commit -m "feat: add blade fallback views"
```

---

## Phase 12: Final Integration & Cleanup

### Task 12.1: Delete Example Files

**Step 1: Remove placeholder files**

```bash
rm -f src/Commands/LaravelFrontdoorCommand.php
rm -f tests/ExampleTest.php
```

**Step 2: Commit**

```bash
git add -A
git commit -m "chore: remove placeholder files"
```

---

### Task 12.2: Update Service Provider Publishables

**Step 1: Update service provider with all publishables**

Ensure `configurePackage` includes:

```php
public function configurePackage(Package $package): void
{
    $package
        ->name('laravel-frontdoor')
        ->hasConfigFile()
        ->hasViews()
        ->hasTranslations()
        ->hasRoute('frontdoor');
}
```

**Step 2: Commit**

```bash
git add src/LaravelFrontdoorServiceProvider.php
git commit -m "chore: finalize publishable assets"
```

---

### Task 12.3: Run Full Test Suite

**Step 1: Run all tests**

Run: `vendor/bin/pest -v`
Expected: All PASS

**Step 2: Run PHPStan**

Run: `composer analyse`
Expected: Pass

**Step 3: Run Pint**

Run: `composer format`

**Step 4: Final commit**

```bash
git add -A
git commit -m "chore: final cleanup and formatting"
```

---

### Task 12.4: Create Translations

**Files:**
- Create: `resources/lang/en/frontdoor.php`

**Step 1: Create lang directory**

```bash
mkdir -p resources/lang/en
```

**Step 2: Create translations**

Create `resources/lang/en/frontdoor.php`:

```php
<?php

return [
    'login' => 'Login',
    'logout' => 'Logout',
    'account' => 'Account',
    'email_label' => 'Email address',
    'code_label' => 'Verification code',
    'continue' => 'Continue',
    'verify' => 'Verify',
    'sending' => 'Sending...',
    'verifying' => 'Verifying...',
    'sign_in_title' => 'Sign in to your account',
    'sign_in_subtitle' => 'Enter your email to receive a login code',
    'code_sent_title' => 'Enter your code',
    'code_sent_subtitle' => 'We sent a 6-digit code to :email',
    'success_title' => "You're signed in!",
    'success_subtitle' => 'Welcome back, :name',
    'use_different_email' => 'Use different email',
    'resend_code' => 'Resend code',
    'error_account_not_found' => 'No account found with this email address.',
    'error_invalid_code' => 'Invalid or expired code. Please try again.',
    'error_too_many_requests' => 'Too many attempts. Please wait :seconds seconds.',
    'error_too_many_attempts' => 'Too many attempts. Please request a new code.',
];
```

**Step 3: Commit**

```bash
git add resources/lang/
git commit -m "feat: add translation strings"
```

---

## Summary

This plan implements Laravel Frontdoor in 12 phases with ~45 bite-sized tasks. Each task is TDD-driven with explicit test → implement → verify → commit cycles.

**Key milestones:**
- Phase 1-2: Contracts and Account Provider (foundation)
- Phase 3: OTP System (security layer)
- Phase 4-5: Auth System (Laravel integration)
- Phase 6-7: Avatar and Mail (polish)
- Phase 8: Feature tests (validation)
- Phase 9-10: UI Components (user experience)
- Phase 11: Routes and Controllers (blade fallback)
- Phase 12: Final integration (cleanup)

**Estimated commits:** 35-40
**Test coverage:** Unit + Feature tests for all core functionality
