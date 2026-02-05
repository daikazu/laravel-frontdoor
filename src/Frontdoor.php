<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor;

use Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity;
use Daikazu\LaravelFrontdoor\Contracts\AccountData;
use Daikazu\LaravelFrontdoor\Contracts\CreatableAccountDriver;
use Daikazu\LaravelFrontdoor\Events\AccountRegistered;
use Daikazu\LaravelFrontdoor\Exceptions\AccountNotFoundException;
use Daikazu\LaravelFrontdoor\Exceptions\RegistrationNotSupportedException;
use Daikazu\LaravelFrontdoor\Otp\OtpManager;
use Daikazu\LaravelFrontdoor\Support\AccountManager;
use Daikazu\LaravelFrontdoor\Support\OtpMailer;
use Daikazu\LaravelFrontdoor\Support\RegistrationField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Frontdoor
{
    public function __construct(
        protected AccountManager $accountManager,
        protected OtpManager $otpManager,
        protected OtpMailer $otpMailer,
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

        $code = $this->otpManager->generate($email);

        $this->otpMailer->send($email, $code, $account);

        return $code;
    }

    /**
     * Send a verification OTP to an email address before registration.
     *
     * If the account already exists, falls through to requestOtp to prevent email enumeration.
     *
     * @throws RegistrationNotSupportedException
     */
    public function requestEmailVerification(string $email): string
    {
        if (! $this->registrationEnabled()) {
            throw new RegistrationNotSupportedException;
        }

        // If account already exists, fall through to normal OTP flow (prevents email enumeration)
        $existing = $this->accountManager->driver()->findByEmail($email);
        if ($existing !== null) {
            return $this->requestOtp($email);
        }

        $code = $this->otpManager->generate($email);

        $this->otpMailer->sendVerification($email, $code);

        return $code;
    }

    /**
     * Verify an OTP code without logging in the user.
     *
     * Used during registration to verify email ownership before showing the registration form.
     */
    public function verifyEmailOnly(string $email, string $code): bool
    {
        return $this->otpManager->verify($email, $code);
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

    public function registrationEnabled(): bool
    {
        return config('frontdoor.registration.enabled', false)
            && $this->accountManager->driver() instanceof CreatableAccountDriver;
    }

    /**
     * Return the registration fields defined by the active account driver.
     *
     * @return RegistrationField[]
     *
     * @throws RegistrationNotSupportedException
     */
    public function registrationFields(): array
    {
        if (! $this->registrationEnabled()) {
            throw new RegistrationNotSupportedException;
        }

        /** @var CreatableAccountDriver $driver */
        $driver = $this->accountManager->driver();

        return $driver->registrationFields();
    }

    /**
     * Register a new account, auto-login the user, and send a welcome email.
     *
     * If the account already exists, falls through to requestEmailVerification to prevent email enumeration.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RegistrationNotSupportedException
     * @throws ValidationException
     */
    public function register(string $email, array $data = []): AccountData
    {
        if (! $this->registrationEnabled()) {
            throw new RegistrationNotSupportedException;
        }

        // If account already exists, fall through to verification flow
        $existing = $this->accountManager->driver()->findByEmail($email);
        if ($existing !== null) {
            $this->requestEmailVerification($email);

            return $existing;
        }

        /** @var CreatableAccountDriver $driver */
        $driver = $this->accountManager->driver();

        // Build validation rules from registration fields
        $rules = [];
        foreach ($driver->registrationFields() as $field) {
            $fieldRules = $field->rules;
            if ($field->required) {
                array_unshift($fieldRules, 'required');
            }
            $rules[$field->name] = $fieldRules;
        }

        if ($rules !== []) {
            Validator::make($data, $rules)->validate();
        }

        $account = $driver->create($email, $data);

        AccountRegistered::dispatch($account);

        // Auto-login the user
        $identity = new FrontdoorIdentity($account);

        /** @var \Daikazu\LaravelFrontdoor\Auth\FrontdoorGuard $guard */
        $guard = Auth::guard('frontdoor');
        $guard->login($identity);

        // Send welcome email (no OTP)
        $this->otpMailer->sendWelcome($email, $account);

        return $account;
    }
}
