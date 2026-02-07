<?php

use Daikazu\LaravelFrontdoor\View\Components\Avatar;
use Daikazu\LaravelFrontdoor\View\Components\NavLogin;

it('Avatar component sets initial, gradient, textColor, and sizeClasses', function () {
    $component = new Avatar(identifier: 'jane@example.com', name: 'Jane Doe', size: 'md');

    expect($component->initial)->toBe('J');
    expect($component->gradient)->toContain('linear-gradient');
    expect($component->textColor)->toBeIn(['#1f2937', '#ffffff']);
    expect($component->sizeClasses)->toBe('w-10 h-10 text-base');
});

it('Avatar component maps xs size correctly', function () {
    $component = new Avatar(identifier: 'test@example.com', size: 'xs');
    expect($component->sizeClasses)->toBe('w-6 h-6 text-xs');
});

it('Avatar component maps sm size correctly', function () {
    $component = new Avatar(identifier: 'test@example.com', size: 'sm');
    expect($component->sizeClasses)->toBe('w-8 h-8 text-sm');
});

it('Avatar component maps lg size correctly', function () {
    $component = new Avatar(identifier: 'test@example.com', size: 'lg');
    expect($component->sizeClasses)->toBe('w-12 h-12 text-lg');
});

it('Avatar component maps xl size correctly', function () {
    $component = new Avatar(identifier: 'test@example.com', size: 'xl');
    expect($component->sizeClasses)->toBe('w-16 h-16 text-xl');
});

it('Avatar component falls back to md for invalid size', function () {
    $component = new Avatar(identifier: 'test@example.com', size: 'invalid');
    expect($component->sizeClasses)->toBe('w-10 h-10 text-base');
});

it('NavLogin component uses config defaults', function () {
    config(['frontdoor.ui.nav.login_label' => 'Sign In']);
    config(['frontdoor.ui.nav.account_route' => '/my-account']);

    $component = new NavLogin;

    expect($component->label)->toBe('Sign In');
    expect($component->accountRoute)->toBe('/my-account');
    expect($component->size)->toBe('md');
});

it('Avatar render returns a view', function () {
    $component = new Avatar(identifier: 'test@example.com');
    expect($component->render())->toBeInstanceOf(\Illuminate\Contracts\View\View::class);
});

it('NavLogin render returns a view', function () {
    $component = new NavLogin;
    expect($component->render())->toBeInstanceOf(\Illuminate\Contracts\View\View::class);
});

it('NavLogin component uses provided props over config', function () {
    config(['frontdoor.ui.nav.login_label' => 'Sign In']);
    config(['frontdoor.ui.nav.account_route' => '/my-account']);

    $component = new NavLogin(
        label: 'Log In',
        accountRoute: '/dashboard',
        size: 'lg',
    );

    expect($component->label)->toBe('Log In');
    expect($component->accountRoute)->toBe('/dashboard');
    expect($component->size)->toBe('lg');
});
