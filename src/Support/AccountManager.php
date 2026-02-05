<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Support;

use Daikazu\LaravelFrontdoor\Contracts\AccountDriver;
use Daikazu\LaravelFrontdoor\Drivers\CacheAccountDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * @method AccountDriver driver(?string $driver = null)
 */
class AccountManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('frontdoor.accounts.driver', 'testing');
    }

    /**
     * Create a driver instance.
     *
     * Resolution order:
     * 1. FQCN — if $driver is a class name, resolve it from the container
     * 2. Config mapping — if drivers.$driver is a class name, resolve that
     * 3. Named driver — fall through to create{Driver}Driver() methods
     */
    protected function createDriver($driver): AccountDriver
    {
        // 1. Direct FQCN (e.g. \App\Frontdoor\SalesforceAccountDriver::class)
        if (class_exists($driver)) {
            return $this->resolveAccountDriver($driver);
        }

        // 2. Config mapping (e.g. 'salesforce' => SalesforceAccountDriver::class)
        $mapped = $this->config->get("frontdoor.accounts.drivers.{$driver}");

        if (is_string($mapped) && class_exists($mapped)) {
            return $this->resolveAccountDriver($mapped);
        }

        // 3. Named driver (e.g. createTestingDriver())
        /** @var AccountDriver */
        return parent::createDriver($driver);
    }

    protected function resolveAccountDriver(string $class): AccountDriver
    {
        $instance = $this->container->make($class);

        if (! $instance instanceof AccountDriver) {
            throw new InvalidArgumentException("Class [{$class}] must implement ".AccountDriver::class.'.');
        }

        return $instance;
    }

    protected function createTestingDriver(): AccountDriver
    {
        /** @var array<string, array<string, mixed>> $users */
        $users = $this->config->get('frontdoor.accounts.drivers.testing.users', []);

        $store = $this->config->get('frontdoor.otp.cache_store');

        return new CacheAccountDriver(Cache::store($store), $users);
    }
}
