<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor;

use Daikazu\LaravelFrontdoor\Auth\FrontdoorGuard;
use Daikazu\LaravelFrontdoor\Auth\FrontdoorUserProvider;
use Daikazu\LaravelFrontdoor\Contracts\OtpStore;
use Daikazu\LaravelFrontdoor\Otp\CacheOtpStore;
use Daikazu\LaravelFrontdoor\Otp\OtpManager;
use Daikazu\LaravelFrontdoor\Support\AccountManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelFrontdoorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-frontdoor')
            ->hasConfigFile('frontdoor')
            ->hasViews()
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->bind(AccountManager::class, function ($app): AccountManager {
            /** @var \Illuminate\Contracts\Container\Container $container */
            $container = $app;

            return new AccountManager($container);
        });

        $this->app->singleton(OtpStore::class, function ($app) {
            /** @var string|null $store */
            $store = config('frontdoor.otp.cache_store');

            /** @var string $prefix */
            $prefix = config('frontdoor.otp.cache_prefix', 'frontdoor:otp:');

            return new CacheOtpStore(
                Cache::store($store),
                $prefix
            );
        });

        $this->app->singleton(OtpManager::class, function ($app): OtpManager {
            /** @var array{length: int, ttl: int, rate_limit: array{max_attempts: int, decay_seconds: int}} $otpConfig */
            $otpConfig = config('frontdoor.otp', [
                'length' => 6,
                'ttl' => 600,
                'rate_limit' => [
                    'max_attempts' => 5,
                    'decay_seconds' => 300,
                ],
            ]);

            /** @var \Illuminate\Contracts\Foundation\Application $container */
            $container = $app;

            /** @var OtpStore $otpStore */
            $otpStore = $container->make(OtpStore::class);

            return new OtpManager($otpStore, $otpConfig);
        });

        $this->app->singleton(Frontdoor::class, function ($app): Frontdoor {
            /** @var \Illuminate\Contracts\Foundation\Application $container */
            $container = $app;

            /** @var AccountManager $accountManager */
            $accountManager = $container->make(AccountManager::class);

            /** @var OtpManager $otpManager */
            $otpManager = $container->make(OtpManager::class);

            /** @var Support\OtpMailer $otpMailer */
            $otpMailer = $container->make(Support\OtpMailer::class);

            return new Frontdoor($accountManager, $otpManager, $otpMailer);
        });

        $this->app->alias(Frontdoor::class, 'frontdoor');
    }

    public function packageBooted(): void
    {
        $this->registerAuthGuard();

        if (config('frontdoor.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/frontdoor.php');
        }

        Blade::componentNamespace('Daikazu\\LaravelFrontdoor\\View\\Components', 'frontdoor');

        if ($this->app->bound('livewire.finder')) {
            \Livewire\Livewire::addNamespace('frontdoor', classNamespace: 'Daikazu\\LaravelFrontdoor\\Livewire');
        }
    }

    protected function registerAuthGuard(): void
    {
        Auth::provider('frontdoor', function ($app, array $config): FrontdoorUserProvider {
            /** @var \Illuminate\Contracts\Foundation\Application $container */
            $container = $app;

            /** @var AccountManager $accountManager */
            $accountManager = $container->make(AccountManager::class);

            /** @var \Daikazu\LaravelFrontdoor\Contracts\AccountDriver $driver */
            $driver = $accountManager->driver();

            return new FrontdoorUserProvider($driver);
        });

        Auth::extend('frontdoor', function ($app, $name, array $config): FrontdoorGuard {
            /** @var \Illuminate\Contracts\Foundation\Application $container */
            $container = $app;

            /** @var \Illuminate\Contracts\Session\Session $session */
            $session = $container->make('session.store');

            return new FrontdoorGuard($session, 'frontdoor_identity');
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
