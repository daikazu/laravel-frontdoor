<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Livewire;

use Daikazu\LaravelFrontdoor\Exceptions\AccountNotFoundException;
use Daikazu\LaravelFrontdoor\Exceptions\RegistrationNotSupportedException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyOtpRequestsException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyVerificationAttemptsException;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Illuminate\Validation\ValidationException;
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

    public bool $showRegistrationPrompt = false;

    public bool $isRegistering = false;

    /** @var array<string, mixed> */
    public array $registrationData = [];

    /** @var array<int, array<string, mixed>> */
    public array $registrationFields = [];

    public function submitEmail(): void
    {
        $this->validate(['email' => 'required|email']);
        $this->errorMessage = null;
        $this->showRegistrationPrompt = false;

        try {
            Frontdoor::requestOtp($this->email);
            $this->step = 'otp';
            $this->resendCountdown = 60;
        } catch (AccountNotFoundException $e) {
            if (Frontdoor::registrationEnabled()) {
                $this->showRegistrationPrompt = true;
            } else {
                $this->errorMessage = 'No account found with this email address.';
            }
        } catch (TooManyOtpRequestsException $e) {
            $this->errorMessage = "Too many attempts. Please wait {$e->retryAfterSeconds} seconds.";
        }
    }

    public function register(): void
    {
        $this->validate(['email' => 'required|email']);
        $this->errorMessage = null;
        $this->showRegistrationPrompt = false;

        try {
            Frontdoor::requestEmailVerification($this->email);
            $this->isRegistering = true;
            $this->step = 'otp';
            $this->resendCountdown = 60;
        } catch (RegistrationNotSupportedException $e) {
            $this->errorMessage = 'Registration is not available at this time.';
        } catch (TooManyOtpRequestsException $e) {
            $this->errorMessage = "Too many attempts. Please wait {$e->retryAfterSeconds} seconds.";
        }
    }

    public function submitCode(): void
    {
        $this->validate(['code' => 'required|digits:6']);
        $this->errorMessage = null;

        try {
            if ($this->isRegistering) {
                if (Frontdoor::verifyEmailOnly($this->email, $this->code)) {
                    $fields = Frontdoor::registrationFields();
                    $this->registrationFields = array_map(fn ($f) => $f->toArray(), $fields);
                    $this->registrationData = [];
                    foreach ($fields as $field) {
                        $this->registrationData[$field->name] = $field->type === 'checkbox' ? false : '';
                    }
                    $this->step = 'register';
                    $this->code = '';
                } else {
                    $this->errorMessage = 'Invalid or expired code. Please try again.';
                }
            } else {
                if (Frontdoor::verify($this->email, $this->code)) {
                    /** @var \Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity|null $user */
                    $user = auth('frontdoor')->user();
                    session()->put('frontdoor.login_success', $user?->getName());

                    $this->redirect(url()->previous('/'));
                } else {
                    $this->errorMessage = 'Invalid or expired code. Please try again.';
                }
            }
        } catch (TooManyVerificationAttemptsException $e) {
            $this->errorMessage = 'Too many attempts. Please request a new code.';
            $this->step = 'email';
            $this->code = '';
        }
    }

    public function submitRegistration(): void
    {
        $this->errorMessage = null;

        try {
            Frontdoor::register($this->email, $this->registrationData);

            /** @var \Daikazu\LaravelFrontdoor\Auth\FrontdoorIdentity|null $user */
            $user = auth('frontdoor')->user();
            session()->put('frontdoor.login_success', $user?->getName());

            $this->redirect(url()->previous('/'));
        } catch (RegistrationNotSupportedException $e) {
            $this->errorMessage = 'Registration is not available at this time.';
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
        } catch (TooManyOtpRequestsException $e) {
            $this->errorMessage = "Too many attempts. Please wait {$e->retryAfterSeconds} seconds.";
        }
    }

    public function resendCode(): void
    {
        $this->code = '';
        $this->errorMessage = null;

        try {
            if ($this->isRegistering) {
                Frontdoor::requestEmailVerification($this->email);
            } else {
                Frontdoor::requestOtp($this->email);
                $this->step = 'otp';
            }
            $this->resendCountdown = 60;
        } catch (AccountNotFoundException $e) {
            $this->errorMessage = 'No account found with this email address.';
        } catch (TooManyOtpRequestsException $e) {
            $this->errorMessage = "Too many attempts. Please wait {$e->retryAfterSeconds} seconds.";
        }
    }

    public function goBack(): void
    {
        $this->step = 'email';
        $this->code = '';
        $this->errorMessage = null;
        $this->showRegistrationPrompt = false;
        $this->isRegistering = false;
        $this->registrationData = [];
        $this->registrationFields = [];
    }

    public function render()
    {
        return view('frontdoor::livewire.login-flow');
    }
}
