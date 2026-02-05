<?php

declare(strict_types=1);

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
