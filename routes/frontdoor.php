<?php

declare(strict_types=1);

use Daikazu\LaravelFrontdoor\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('frontdoor.routes.middleware', ['web']))
    ->prefix(config('frontdoor.routes.prefix', 'frontdoor'))
    ->name('frontdoor.')
    ->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Blade fallback routes (when Livewire not available)
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'sendOtp'])->name('send-otp');
        Route::get('/verify', [AuthController::class, 'showVerify'])->name('verify');
        Route::post('/verify', [AuthController::class, 'verifyOtp'])->name('verify-otp');
        Route::get('/register', [AuthController::class, 'showRegister'])->name('show-register');
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::get('/register/complete', [AuthController::class, 'showCompleteRegistration'])->name('show-register-complete');
        Route::post('/register/complete', [AuthController::class, 'completeRegistration'])->name('register-complete');
    });
