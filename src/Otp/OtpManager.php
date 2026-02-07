<?php

declare(strict_types=1);

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
        /** @var string $key */
        $key = config('app.key');

        return hash_hmac('sha256', $code, $key);
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
