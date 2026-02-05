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
    | Account Driver
    |--------------------------------------------------------------------------
    |
    | The driver determines where user accounts are looked up.
    |
    | Built-in:
    |   'testing' — Cache-backed driver with seed users. Supports registration.
    |               For development and trying out the package.
    |
    | Custom drivers can be registered two ways:
    |
    |   1. Named driver — add an entry to the drivers array below:
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
                    // Seed users for the testing driver. Add emails here to
                    // try out the package without creating a custom driver.
                    //
                    //                     'jane@example.com' => [
                    //                         'name' => 'Jane Doe',
                    //                         'phone' => '+1-555-0100',
                    //                         'metadata' => ['role' => 'admin'],
                    //                     ],
                ],
            ],

            // Example: register a custom named driver
            // 'salesforce' => \App\Frontdoor\SalesforceAccountDriver::class,
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
        'enabled' => true,
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
        'verification_mailable' => \Daikazu\LaravelFrontdoor\Mail\OtpMail::class,
        'verification_subject' => 'Verify your email address',
        'welcome_mailable' => \Daikazu\LaravelFrontdoor\Mail\WelcomeMail::class,
        'welcome_subject' => 'Welcome to '.env('APP_NAME', 'our app'),
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
