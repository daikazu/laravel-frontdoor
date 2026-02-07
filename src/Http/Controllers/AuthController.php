<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Http\Controllers;

use Daikazu\LaravelFrontdoor\Exceptions\AccountNotFoundException;
use Daikazu\LaravelFrontdoor\Exceptions\RegistrationNotSupportedException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyOtpRequestsException;
use Daikazu\LaravelFrontdoor\Exceptions\TooManyVerificationAttemptsException;
use Daikazu\LaravelFrontdoor\Facades\Frontdoor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('frontdoor::blade.login');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        try {
            $emailInput = $request->input('email');
            $email = is_string($emailInput) ? $emailInput : '';

            Frontdoor::requestOtp($email);

            session(['frontdoor_email' => $email]);

            return redirect()->route('frontdoor.verify');
        } catch (AccountNotFoundException $e) {
            if (Frontdoor::registrationEnabled()) {
                $emailInput = $request->input('email');
                $email = is_string($emailInput) ? $emailInput : '';

                return redirect()->route('frontdoor.show-register', ['email' => $email]);
            }

            return back()->withErrors(['email' => 'No account found with this email address.']);
        } catch (TooManyOtpRequestsException $e) {
            return back()->withErrors(['email' => "Too many attempts. Please wait {$e->retryAfterSeconds} seconds."]);
        }
    }

    public function showVerify(): View|RedirectResponse
    {
        if (! session()->has('frontdoor_email')) {
            return redirect()->route('frontdoor.login');
        }

        return view('frontdoor::blade.verify', [
            'email' => session('frontdoor_email'),
            'isRegistering' => session('frontdoor_registering', false),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|digits:6']);

        $emailSession = session('frontdoor_email');
        $email = is_string($emailSession) ? $emailSession : null;

        if (! $email) {
            return redirect()->route('frontdoor.login');
        }

        try {
            if (session('frontdoor_registering', false)) {
                $codeInput = $request->input('code');
                $code = is_string($codeInput) ? $codeInput : '';

                if (Frontdoor::verifyEmailOnly($email, $code)) {
                    session(['frontdoor_email_verified' => true]);

                    return redirect()->route('frontdoor.show-register-complete');
                }

                return back()->withErrors(['code' => 'Invalid or expired code.']);
            }

            $codeInput = $request->input('code');
            $code = is_string($codeInput) ? $codeInput : '';

            if (Frontdoor::verify($email, $code)) {
                session()->forget(['frontdoor_email', 'frontdoor_registering']);

                return redirect()->intended('/');
            }

            return back()->withErrors(['code' => 'Invalid or expired code.']);
        } catch (TooManyVerificationAttemptsException $e) {
            session()->forget(['frontdoor_email', 'frontdoor_registering']);

            return redirect()->route('frontdoor.login')
                ->withErrors(['email' => 'Too many attempts. Please request a new code.']);
        }
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        if (! Frontdoor::registrationEnabled()) {
            return redirect()->route('frontdoor.login');
        }

        $emailQuery = $request->query('email', '');
        $email = is_string($emailQuery) ? $emailQuery : '';

        return view('frontdoor::blade.register', [
            'email' => $email,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $emailInput = $request->input('email');
        $email = is_string($emailInput) ? $emailInput : '';

        try {
            Frontdoor::requestEmailVerification($email);

            /** @var array<string, mixed> $sessionData */
            $sessionData = [
                'frontdoor_email' => $email,
                'frontdoor_registering' => true,
            ];
            session($sessionData);

            return redirect()->route('frontdoor.verify');
        } catch (RegistrationNotSupportedException $e) {
            return back()->withErrors(['email' => 'Registration is not available at this time.'])->withInput();
        } catch (TooManyOtpRequestsException $e) {
            return back()->withErrors(['email' => "Too many attempts. Please wait {$e->retryAfterSeconds} seconds."])->withInput();
        }
    }

    public function showCompleteRegistration(): View|RedirectResponse
    {
        if (! session('frontdoor_email_verified') || ! session('frontdoor_email')) {
            return redirect()->route('frontdoor.login');
        }

        return view('frontdoor::blade.register-complete', [
            'email' => session('frontdoor_email'),
            'fields' => Frontdoor::registrationFields(),
        ]);
    }

    public function completeRegistration(Request $request): RedirectResponse
    {
        if (! session('frontdoor_email_verified') || ! session('frontdoor_email')) {
            return redirect()->route('frontdoor.login');
        }

        $emailSession = session('frontdoor_email');
        $email = is_string($emailSession) ? $emailSession : '';

        /** @var array<string, mixed> $data */
        $data = $request->except(['_token']);

        try {
            Frontdoor::register($email, $data);

            session()->forget(['frontdoor_email', 'frontdoor_registering', 'frontdoor_email_verified']);

            return redirect()->intended('/');
        } catch (RegistrationNotSupportedException $e) {
            return back()->withErrors(['email' => 'Registration is not available at this time.'])->withInput();
        } catch (ValidationException $e) {
            throw $e;
        } catch (TooManyOtpRequestsException $e) {
            return back()->withErrors(['email' => "Too many attempts. Please wait {$e->retryAfterSeconds} seconds."])->withInput();
        }
    }

    public function logout(): RedirectResponse
    {
        /** @var \Daikazu\LaravelFrontdoor\Auth\FrontdoorGuard $guard */
        $guard = Auth::guard('frontdoor');
        $guard->logout();

        return redirect('/');
    }
}
