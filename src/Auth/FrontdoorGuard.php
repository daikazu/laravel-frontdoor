<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Auth;

use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Events\LoginSucceeded;
use Daikazu\LaravelFrontdoor\Events\LogoutSucceeded;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use InvalidArgumentException;

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

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(?Authenticatable $user): static
    {
        if ($user === null) {
            $this->user = null;

            return $this;
        }

        if (! $user instanceof FrontdoorIdentity) {
            throw new InvalidArgumentException('User must be an instance of FrontdoorIdentity');
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
