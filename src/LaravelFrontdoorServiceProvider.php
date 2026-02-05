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
        $this->app->bind(AccountManager::class, function ($app) {
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
                config('frontdoor.otp', [
                    'length' => 6,
                    'ttl' => 600,
                    'rate_limit' => [
                        'max_attempts' => 5,
                        'decay_seconds' => 300,
                    ],
                ])
            );
        });

        $this->app->singleton(Frontdoor::class, function ($app) {
            return new Frontdoor(
                $app->make(AccountManager::class),
                $app->make(OtpManager::class),
                $app->make(Support\OtpMailer::class)
            );
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
