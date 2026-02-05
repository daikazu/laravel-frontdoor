<picture>
   <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
   <img alt="Logo for Frontdoor" src="art/header-light.png">
</picture>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daikazu/laravel-frontdoor.svg?style=flat-square)](https://packagist.org/packages/daikazu/laravel-frontdoor)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/laravel-frontdoor/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/daikazu/laravel-frontdoor/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/daikazu/laravel-frontdoor/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/daikazu/laravel-frontdoor/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/daikazu/laravel-frontdoor.svg?style=flat-square)](https://packagist.org/packages/daikazu/laravel-frontdoor)

# Laravel Frontdoor
A driver base Passwordless authentication for Laravel applications. Users log in by receiving a one-time code via email. No database migrations required. Session-based authentication with extensible account providers.

## Features

- **Passwordless Authentication** - Users log in with one-time codes sent to their email
- **Driver-Based Account System** - Built-in testing driver to get started, then register your own driver class in config
- **Optional Registration** - Enable self-service account creation with compatible drivers
- **Zero Database Requirements** - Works out of the box with session authentication and the testing driver
- **Livewire Integration** - Reactive UI components with Alpine.js fallback
- **Deterministic Avatars** - Beautiful gradient avatars generated from email hashes
- **Rate Limiting** - Built-in protection against brute force attacks
- **Event System** - Listen to authentication and registration events throughout the flow

## Requirements

- PHP 8.4+
- Laravel 12+
- Livewire 3.0+ or 4.0+

## Installation

Install the package via Composer:

```bash
composer require daikazu/laravel-frontdoor
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-frontdoor-config"
```

## Quick Start

Get up and running in 2 minutes using the built-in testing driver.

### 1. Add Seed Users

Open `config/frontdoor.php` and add email addresses to try the package with:

```php
'accounts' => [
    'driver' => 'testing',

    'drivers' => [
        'testing' => [
            'users' => [
                'jane@example.com' => [
                    'name' => 'Jane Doe',
                ],
                'john@example.com' => [
                    'name' => 'John Smith',
                ],
            ],
        ],
    ],
],
```

### 2. Add the Login Component

Add the navigation component to your layout:

```blade
<x-frontdoor::nav-login />
```

That's it! Users can now:
1. Click the login button
2. Enter their email address
3. Receive a one-time code via email
4. Enter the code to log in

New users (when registration is enabled) follow a slightly different flow:
1. Enter their email — prompted to create an account
2. Verify email ownership via OTP
3. Fill in the registration form
4. Account is created and user is automatically logged in

## Account Drivers

Laravel Frontdoor uses a **driver-based system** for looking up user accounts. The active driver is set via the `accounts.driver` config key and determines where accounts are stored and looked up.

When a user attempts to log in:

1. The email is passed to the driver's `findByEmail()` method
2. The driver returns an `AccountData` object if the account exists, or `null` if not
3. If found, an OTP is generated and emailed
4. After OTP verification, the user is authenticated with a session

### Testing Driver (Default)

The package ships with a `testing` driver for development and trying out the package. It stores seed users from your config and any new registrations in cache. It is not intended for production use.

```php
'accounts' => [
    'driver' => 'testing',

    'drivers' => [
        'testing' => [
            'users' => [
                'admin@example.com' => [
                    'name' => 'Admin User',
                    'phone' => '+1-555-0100',
                    'metadata' => ['role' => 'admin'],
                ],
            ],
        ],
    ],
],
```

The testing driver supports registration out of the box. To try it, enable registration in config:

```php
'registration' => [
    'enabled' => true,
],
```

New accounts registered through the UI are stored in cache and persist until the cache is cleared.

### Using Your Own Driver

For production use, create a driver class that implements `AccountDriver` and register it in config. There are two ways to do this:

**Option A: Named driver** — add an entry to the `drivers` array and reference it by name:

```php
'accounts' => [
    'driver' => 'salesforce',

    'drivers' => [
        'salesforce' => \App\Frontdoor\SalesforceAccountDriver::class,
    ],
],
```

**Option B: FQCN** — set the driver directly to the class name:

```php
'accounts' => [
    'driver' => \App\Frontdoor\SalesforceAccountDriver::class,
],
```

Both approaches resolve the class from Laravel's service container automatically. Named drivers are useful when you want to switch between drivers via environment variables (e.g. `FRONTDOOR_ACCOUNT_DRIVER=salesforce`).

#### Creating a Driver (Sign-in Only)

Implement the `AccountDriver` interface with two methods:

```php
<?php

namespace App\Frontdoor;

use App\Models\User;
use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\AccountDriver;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

class DatabaseAccountDriver implements AccountDriver
{
    public function findByEmail(string $email): ?AccountData
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return null;
        }

        return new SimpleAccountData(
            id: (string) $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            avatarUrl: $user->avatar_url,
            metadata: ['role' => $user->role],
        );
    }

    public function exists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }
}
```

Then register it in config (either approach works):

```php
// Named driver
'accounts' => [
    'driver' => 'database',
    'drivers' => [
        'database' => \App\Frontdoor\DatabaseAccountDriver::class,
    ],
],

// Or FQCN
'accounts' => [
    'driver' => \App\Frontdoor\DatabaseAccountDriver::class,
],
```

That's it. No service provider registration needed.

#### Adding Registration Support

To support registration, implement `CreatableAccountDriver` instead. This extends `AccountDriver` with two additional methods: `registrationFields()` defines the form fields shown to the user, and `create()` handles account creation with the submitted data.

```php
<?php

namespace App\Frontdoor;

use App\Models\User;
use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\CreatableAccountDriver;
use Daikazu\LaravelFrontdoor\Support\RegistrationField;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

class DatabaseAccountDriver implements CreatableAccountDriver
{
    public function findByEmail(string $email): ?AccountData
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return null;
        }

        return new SimpleAccountData(
            id: (string) $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            avatarUrl: $user->avatar_url,
            metadata: ['role' => $user->role],
        );
    }

    public function exists(string $email): bool
    {
        return User::where('email', $email)->exists();
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
            new RegistrationField(
                name: 'phone',
                label: 'Phone number',
                type: 'tel',
                required: false,
                rules: ['string', 'max:20'],
            ),
        ];
    }

    public function create(string $email, array $data): AccountData
    {
        $user = User::create([
            'email' => $email,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        return new SimpleAccountData(
            id: (string) $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
        );
    }
}
```

Then enable registration in config:

```php
'accounts' => [
    'driver' => 'database',
    'drivers' => [
        'database' => \App\Frontdoor\DatabaseAccountDriver::class,
    ],
],

'registration' => [
    'enabled' => true,
],
```

When a user tries to log in with an email that doesn't exist, they'll see: "No account found. Would you like to create one?" After clicking "Create account", a verification OTP is sent to confirm email ownership. Once verified, a registration form is displayed with the fields defined by `registrationFields()`. The submitted data is validated against each field's rules, then passed to `create()`. The user is automatically logged in and a welcome email is sent.

#### Registration Field Types

The `RegistrationField` value object supports these field types:

| Type | HTML Element | Notes |
|------|-------------|-------|
| `text` | `<input type="text">` | Default type |
| `email` | `<input type="email">` | |
| `tel` | `<input type="tel">` | |
| `textarea` | `<textarea>` | |
| `select` | `<select>` | Requires `options` array |
| `checkbox` | `<input type="checkbox">` | |

Example with all field types:

```php
public function registrationFields(): array
{
    return [
        new RegistrationField(
            name: 'name',
            label: 'Full name',
            required: true,
            rules: ['string', 'max:255'],
        ),
        new RegistrationField(
            name: 'department',
            label: 'Department',
            type: 'select',
            required: true,
            rules: ['string', 'in:engineering,marketing,sales'],
            options: [
                'engineering' => 'Engineering',
                'marketing' => 'Marketing',
                'sales' => 'Sales',
            ],
        ),
        new RegistrationField(
            name: 'agree_terms',
            label: 'I agree to the terms of service',
            type: 'checkbox',
            required: true,
            rules: ['accepted'],
        ),
    ];
}
```

#### Drivers with Constructor Dependencies

If your driver needs constructor arguments that can't be auto-resolved, bind it in the container:

```php
// In AppServiceProvider
$this->app->bind(ApiAccountDriver::class, function ($app) {
    return new ApiAccountDriver(
        apiUrl: config('services.user_api.url'),
        apiKey: config('services.user_api.key'),
    );
});
```

Then reference it in config using either approach:

```php
// Named driver
'driver' => 'api',
'drivers' => ['api' => \App\Frontdoor\ApiAccountDriver::class],

// Or FQCN
'driver' => \App\Frontdoor\ApiAccountDriver::class,
```

### The AccountData Interface

Drivers return `AccountData` objects. In most cases, use the built-in `SimpleAccountData` DTO:

```php
new SimpleAccountData(
    id: '1',
    name: 'Jane Doe',
    email: 'jane@example.com',
    phone: '+1-555-0100',       // optional
    avatarUrl: null,             // optional, falls back to generated gradient
    metadata: ['role' => 'admin'], // optional
);
```

If you need custom behavior (computed names, Gravatar URLs, etc.), implement the `AccountData` interface directly. See the interface for the full list of required methods: `getId()`, `getName()`, `getEmail()`, `getPhone()`, `getAvatarUrl()`, `getMetadata()`, `getInitial()`, `toArray()`.

## Registration

Registration allows users to create accounts through the login flow. When enabled, users who attempt to log in with an email that doesn't exist will see an option to create an account.

### How Registration Works

1. User enters their email address
2. Driver's `findByEmail()` returns `null` (account doesn't exist)
3. UI displays: "No account found. Would you like to create one?"
4. User clicks "Create account"
5. A verification OTP is sent to the email address
6. User enters the code to verify email ownership
7. A registration form is shown with fields defined by the driver's `registrationFields()` method (email is locked)
8. User fills in the form and submits
9. Data is validated against each field's rules
10. Driver's `create()` is called with the email and form data
11. User is automatically logged in
12. A welcome email is sent (no OTP)
13. `AccountRegistered` event is dispatched

### Requirements

For registration to work, **both** of these conditions must be met:

1. `registration.enabled` must be `true` in config
2. The active driver must implement `CreatableAccountDriver` (not just `AccountDriver`)

If either condition is false, the registration prompt will not appear and users cannot self-register.

### Configuration

```php
'registration' => [
    'enabled' => false,  // Set to true to enable registration
],
```

### Driver Compatibility

| Driver | Supports Registration | Notes |
|--------|----------------------|-------|
| `testing` (built-in) | Yes | For development only |
| Your class implementing `AccountDriver` | No | Sign-in only |
| Your class implementing `CreatableAccountDriver` | Yes | Sign-in + registration |

### Customizing Registration Emails

Registration uses three configurable mailables:

```php
'mail' => [
    // Standard login OTP
    'mailable' => \Daikazu\LaravelFrontdoor\Mail\OtpMail::class,
    'subject' => 'Your login code',

    // Email verification OTP (sent before registration form)
    'verification_mailable' => \Daikazu\LaravelFrontdoor\Mail\OtpMail::class,
    'verification_subject' => 'Verify your email address',

    // Welcome email (sent after account creation, no OTP)
    'welcome_mailable' => \Daikazu\LaravelFrontdoor\Mail\WelcomeMail::class,
    'welcome_subject' => 'Welcome to ' . env('APP_NAME', 'our app'),

    'from' => [
        'address' => env('FRONTDOOR_MAIL_FROM', env('MAIL_FROM_ADDRESS')),
        'name' => env('FRONTDOOR_MAIL_FROM_NAME', env('MAIL_FROM_NAME')),
    ],
],
```

The verification mailable must implement `OtpMailable`. The welcome mailable is a plain `Mailable` that receives the account data via `setAccount()` — it does not contain an OTP code.

### Reacting to Registration

Listen for the `AccountRegistered` event to perform post-registration actions:

```php
use Daikazu\LaravelFrontdoor\Events\AccountRegistered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(function (AccountRegistered $event) {
    Log::info("New user registered: {$event->account->getEmail()}");

    // Send to analytics, assign default roles, create onboarding tasks, etc.
});
```

To restrict who can register (e.g., only company emails), add validation rules in your driver's `registrationFields()` or validation logic in your `create()` method.

## UI Components

### Navigation Component

The `nav-login` component provides a complete authentication UI:

```blade
<x-frontdoor::nav-login />
```

**When not authenticated:** Displays a login button that opens the OTP flow (modal or page, depending on `ui.mode`).

**When authenticated:** Displays a user dropdown with:
- User's name and email
- Avatar (gradient or custom)
- Optional account page link
- Logout button

#### Component Props

```blade
<x-frontdoor::nav-login
    label="Sign In"                          {{-- Custom login button text --}}
    :account-route="route('profile')"        {{-- Link to account page --}}
    size="lg"                                {{-- Avatar size: sm, md, lg --}}
/>
```

### Modal vs Page Mode

Control the authentication experience via `ui.mode`:

**Modal mode** (default):
```php
'ui' => [
    'mode' => 'modal',  // Opens login flow in an overlay
],
```

**Page mode:**
```php
'ui' => [
    'mode' => 'page',  // Redirects to full-page login form
],
```

### Protecting Routes

Use the `frontdoor` guard in middleware:

```php
Route::middleware('auth:frontdoor')->group(function () {
    Route::get('/dashboard', function () {
        $user = auth('frontdoor')->user();
        return view('dashboard', ['user' => $user]);
    });
});
```

Check authentication in Blade:

```blade
@auth('frontdoor')
    <p>Welcome, {{ auth('frontdoor')->user()->name }}!</p>
@endauth

@guest('frontdoor')
    <p>Please log in to continue.</p>
@endguest
```

### Accessing User Data

The authenticated user exposes account data as properties:

```php
$user = auth('frontdoor')->user();

$user->id;              // Unique identifier
$user->name;            // Display name
$user->email;           // Email address
$user->phone;           // Phone number (or null)
$user->avatar_url;      // Avatar URL (or null)
$user->initial;         // First letter of name
$user->metadata;        // Full metadata array
```

Metadata keys are accessible directly as properties too. For example, if your driver returns `metadata: ['role' => 'admin', 'company' => 'Acme']`:

```php
$user->role;            // 'admin'
$user->company;         // 'Acme'
$user->unknown_key;     // null (key not in metadata)
```

## Facade API

The `Frontdoor` facade provides programmatic access to all authentication features:

```php
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
```

### Available Methods

#### `requestOtp(string $email): string`

Request an OTP code for an email address. Generates a code, emails it to the user, and returns the code.

```php
$code = Frontdoor::requestOtp('user@example.com');
// Email is sent with 6-digit code
```

Throws `AccountNotFoundException` if the account doesn't exist and registration is disabled.

#### `verify(string $email, string $code): bool`

Verify an OTP code and log the user in.

```php
$success = Frontdoor::verify('user@example.com', '123456');

if ($success) {
    // User is now authenticated
    $user = auth('frontdoor')->user();
}
```

Returns `false` if the code is invalid or expired.

#### `loginAs(string $email): bool`

Log in a user directly without requiring an OTP. Useful for testing or admin impersonation.

```php
Frontdoor::loginAs('user@example.com');
// User is now authenticated
```

Returns `false` if the account doesn't exist.

#### `registrationFields(): RegistrationField[]`

Get the registration form fields defined by the active account driver.

```php
$fields = Frontdoor::registrationFields();

foreach ($fields as $field) {
    echo $field->name;     // e.g. 'name'
    echo $field->label;    // e.g. 'Full name'
    echo $field->type;     // e.g. 'text'
    echo $field->required; // e.g. true
}
```

Throws `RegistrationNotSupportedException` if registration is not enabled or the driver doesn't support it.

#### `requestEmailVerification(string $email): string`

Send a verification OTP to an email address before registration. Used to confirm email ownership before showing the registration form.

```php
$code = Frontdoor::requestEmailVerification('newuser@example.com');
// Verification email sent with 6-digit code
```

If the email already exists, falls through to `requestOtp()` silently (prevents email enumeration). Throws `RegistrationNotSupportedException` if registration is not enabled.

#### `verifyEmailOnly(string $email, string $code): bool`

Verify an OTP code without logging in the user. Used during registration to confirm email ownership.

```php
$verified = Frontdoor::verifyEmailOnly('newuser@example.com', '123456');

if ($verified) {
    // Email is verified, show registration form
}
```

Returns `false` if the code is invalid or expired. Does **not** log in the user.

#### `register(string $email, array $data = []): AccountData`

Create a new account, auto-login the user, and send a welcome email. Validates `$data` against the rules defined by the driver's `registrationFields()`. Only works if registration is enabled and the driver supports it.

```php
try {
    $account = Frontdoor::register('newuser@example.com', [
        'name' => 'New User',
    ]);
    // User is now logged in, welcome email sent
    echo $account->getName(); // 'New User'
} catch (\Illuminate\Validation\ValidationException $e) {
    // Required fields missing or invalid
} catch (\Daikazu\LaravelFrontdoor\Exceptions\RegistrationNotSupportedException $e) {
    // Registration not enabled or driver doesn't support it
}
```

If the email already exists, falls through to `requestEmailVerification()` silently (prevents email enumeration).

#### `registrationEnabled(): bool`

Check if registration is enabled and supported by the current driver.

```php
if (Frontdoor::registrationEnabled()) {
    // Show registration UI
}
```

#### `accounts(): AccountManager`

Access the account manager to interact with drivers.

```php
$manager = Frontdoor::accounts();

// Find an account
$account = $manager->driver()->findByEmail('user@example.com');

// Check if account exists
$exists = $manager->driver()->exists('user@example.com');
```

#### `otp(): OtpManager`

Access the OTP manager for low-level OTP operations.

```php
$otpManager = Frontdoor::otp();

// Generate a code
$code = $otpManager->generate('user@example.com');

// Verify a code
$valid = $otpManager->verify('user@example.com', '123456');

// Delete a code
$otpManager->delete('user@example.com');
```

## Configuration Reference

Complete configuration options in `config/frontdoor.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The guard name used for Frontdoor authentication. This is registered
    | automatically and used for all authentication checks.
    |
    */
    'guard' => 'frontdoor',

    /*
    |--------------------------------------------------------------------------
    | Account Provider
    |--------------------------------------------------------------------------
    |
    | The driver determines where user accounts are looked up.
    |
    | Built-in:
    |   'testing' - Cache-backed driver with seed users. Supports registration.
    |               For development and trying out the package.
    |
    | Custom drivers can be registered two ways:
    |
    |   1. Named driver — add an entry to the drivers array:
    |      'driver' => 'salesforce',
    |      'drivers' => ['salesforce' => \App\Frontdoor\SalesforceProvider::class]
    |
    |   2. FQCN — set driver directly to the class name:
    |      'driver' => \App\Frontdoor\SalesforceProvider::class
    |
    | The class must implement AccountDriver (sign-in only)
    | or CreatableAccountDriver (sign-in + registration).
    |
    */
    'accounts' => [
        'driver' => env('FRONTDOOR_ACCOUNT_DRIVER', 'testing'),

        'drivers' => [
            'testing' => [
                'users' => [
                    // Seed users for the testing driver.
                    // 'email@example.com' => [
                    //     'name' => 'User Name',
                    //     'phone' => '+1-555-0100',  // optional
                    //     'metadata' => ['role' => 'admin'],  // optional
                    // ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    |
    | When enabled, users who attempt to log in without an existing account
    | will be offered the option to create one. The active account driver
    | must implement CreatableAccountDriver for this to work.
    |
    */
    'registration' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP Settings
    |--------------------------------------------------------------------------
    |
    | Configure one-time password generation and validation behavior.
    |
    */
    'otp' => [
        'length' => 6,                     // Number of digits in the code
        'ttl' => 600,                      // Time-to-live in seconds (10 minutes)
        'cache_store' => null,             // Cache store (null = default)
        'cache_prefix' => 'frontdoor:otp:',

        'rate_limit' => [
            'max_attempts' => 5,           // Max OTP requests per window
            'decay_seconds' => 300,        // Rate limit window (5 minutes)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Settings
    |--------------------------------------------------------------------------
    |
    | Configure email delivery. Separate mailables for login OTP,
    | registration verification OTP, and post-registration welcome.
    |
    */
    'mail' => [
        'mailable' => \Daikazu\LaravelFrontdoor\Mail\OtpMail::class,
        'from' => [
            'address' => env('FRONTDOOR_MAIL_FROM', env('MAIL_FROM_ADDRESS')),
            'name' => env('FRONTDOOR_MAIL_FROM_NAME', env('MAIL_FROM_NAME')),
        ],
        'subject' => 'Your login code',

        // Email verification OTP (sent before registration form)
        'verification_mailable' => \Daikazu\LaravelFrontdoor\Mail\OtpMail::class,
        'verification_subject' => 'Verify your email address',

        // Welcome email (sent after account creation, no OTP)
        'welcome_mailable' => \Daikazu\LaravelFrontdoor\Mail\WelcomeMail::class,
        'welcome_subject' => 'Welcome to ' . env('APP_NAME', 'our app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | Control the authentication UI behavior and appearance.
    |
    */
    'ui' => [
        'mode' => 'modal',                 // 'modal' (overlay) or 'page' (redirect)
        'prefer_livewire' => true,         // Use Livewire when available
        'login_route' => '/login',         // Fallback login route

        'nav' => [
            'login_label' => 'Login',
            'account_label' => 'Account',
            'logout_label' => 'Logout',
            'account_route' => null,       // Optional account page route
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Avatar Settings
    |--------------------------------------------------------------------------
    |
    | Configure deterministic avatar generation. Avatars are generated from
    | email hashes using HSL gradients.
    |
    */
    'avatar' => [
        'algorithm' => 'gradient',         // Avatar generation algorithm
        'saturation' => 65,                // HSL saturation percentage
        'lightness' => 55,                 // HSL lightness percentage
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configure authentication routes. Set enabled to false to disable
    | automatic route registration.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'frontdoor',
        'middleware' => ['web'],
    ],
];
```

## Events

Laravel Frontdoor dispatches events throughout the authentication and registration flow:

| Event | Description | Properties |
|-------|-------------|------------|
| `OtpRequested` | User requested an OTP code | `email` |
| `OtpVerified` | OTP code successfully verified | `email` |
| `LoginSucceeded` | User successfully logged in | `identity` (AccountData) |
| `LoginFailed` | Login attempt failed | `email`, `reason` |
| `LogoutSucceeded` | User logged out | `identity` (AccountData) |
| `AccountRegistered` | New account created via registration | `account` (AccountData) |

### Listening to Events

Register listeners in `EventServiceProvider`:

```php
use Daikazu\LaravelFrontdoor\Events\LoginSucceeded;
use Daikazu\LaravelFrontdoor\Events\AccountRegistered;

protected $listen = [
    LoginSucceeded::class => [
        LogUserLogin::class,
    ],
    AccountRegistered::class => [
        SendWelcomeNotification::class,
    ],
];
```

Or use closures in `AppServiceProvider`:

```php
use Daikazu\LaravelFrontdoor\Events\OtpRequested;
use Daikazu\LaravelFrontdoor\Events\AccountRegistered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

Event::listen(function (OtpRequested $event) {
    Log::info("OTP requested for {$event->email}");
});

Event::listen(function (AccountRegistered $event) {
    Log::info("New account created: {$event->account->getEmail()}");

    // Send to analytics, create welcome tasks, etc.
});
```

## Customization

### Publishing Views

Customize the UI by publishing the views:

```bash
php artisan vendor:publish --tag="laravel-frontdoor-views"
```

Views will be published to `resources/views/vendor/frontdoor/`.

Available views:
- `components/nav-login.blade.php` - Navigation login component
- `livewire/login-flow.blade.php` - Livewire login flow
- `livewire/register-fields.blade.php` - Dynamic registration form fields partial
- `blade/login.blade.php` - Blade fallback login page
- `blade/register.blade.php` - Blade email verification prompt page
- `blade/register-complete.blade.php` - Blade registration form page
- `blade/verify.blade.php` - Blade OTP verification page
- `mail/otp.blade.php` - OTP email template (login and verification)
- `mail/welcome.blade.php` - Welcome email template (post-registration, no OTP)

### Custom OTP Email

Create your own mailable implementing the `OtpMailable` contract. The contract requires three setter methods:

```php
<?php

namespace App\Mail;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\OtpMailable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class BrandedOtpMail extends Mailable implements OtpMailable
{
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
        return new Envelope(subject: 'Your Login Code');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.branded-otp',
            with: [
                'code' => $this->code,
                'account' => $this->account,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}
```

Update the configuration:

```php
'mail' => [
    'mailable' => \App\Mail\BrandedOtpMail::class,
    // ...
],
```

### Custom Avatar URL

Return a custom avatar URL from your `AccountData` implementation:

```php
public function getAvatarUrl(): ?string
{
    // Use Gravatar
    $hash = md5(strtolower(trim($this->email)));
    return "https://www.gravatar.com/avatar/{$hash}?d=mp&s=200";

    // Or use UI Avatars
    return "https://ui-avatars.com/api/?name=" . urlencode($this->name) . "&background=random";

    // Or return stored avatar path
    return $this->avatarPath ? asset($this->avatarPath) : null;
}
```

If `getAvatarUrl()` returns `null`, Frontdoor will generate a deterministic gradient avatar based on the email hash.

### Changing Avatar Generation

Modify gradient avatar settings:

```php
'avatar' => [
    'algorithm' => 'gradient',
    'saturation' => 75,  // Higher saturation = more vivid colors
    'lightness' => 50,   // Lower lightness = darker colors
],
```

## Testing

Run the test suite:

```bash
composer test
```

Run a specific test:

```bash
composer test -- --filter=OtpFlowTest
```

Run tests with coverage:

```bash
composer test-coverage
```

Run static analysis:

```bash
composer analyse
```

Format code:

```bash
composer format
```

### Testing Your Application

Use `loginAs()` to bypass OTP verification in tests:

```php
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;

it('allows authenticated users to view dashboard', function () {
    Frontdoor::loginAs('user@example.com');

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard');
});
```

Use Mail and Cache fakes to test OTP flow:

```php
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

it('sends OTP email when user requests code', function () {
    Mail::fake();

    Frontdoor::requestOtp('user@example.com');

    Mail::assertSent(OtpMail::class, function ($mail) {
        return $mail->hasTo('user@example.com');
    });
});
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mike Wall](https://github.com/daikazu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
