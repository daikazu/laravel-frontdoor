<?php

use Daikazu\LaravelFrontdoor\Mail\OtpMail;
use Daikazu\LaravelFrontdoor\Mail\WelcomeMail;
use Daikazu\LaravelFrontdoor\Support\SimpleAccountData;

it('OtpMail envelope uses config from and subject', function () {
    config(['frontdoor.mail.from.address' => 'noreply@myapp.com']);
    config(['frontdoor.mail.from.name' => 'My App']);
    config(['frontdoor.mail.subject' => 'Your OTP Code']);

    $mail = new OtpMail;
    $envelope = $mail->envelope();

    expect($envelope->subject)->toBe('Your OTP Code');
    expect($envelope->from->address)->toBe('noreply@myapp.com');
    expect($envelope->from->name)->toBe('My App');
});

it('OtpMail content passes code, account, expiresInMinutes, and appName', function () {
    config(['app.name' => 'TestApp']);

    $account = new SimpleAccountData(id: '1', name: 'Jane', email: 'jane@example.com');

    $mail = new OtpMail;
    $mail->setCode('123456')
        ->setAccount($account)
        ->setExpiresInMinutes(5);

    $content = $mail->content();

    expect($content->markdown)->toBe('frontdoor::mail.otp');
    expect($content->with)->toBe([
        'code' => '123456',
        'account' => $account,
        'expiresInMinutes' => 5,
        'appName' => 'TestApp',
    ]);
});

it('OtpMail falls back to mail.from config when frontdoor config is null', function () {
    config(['frontdoor.mail.from.address' => null]);
    config(['frontdoor.mail.from.name' => null]);
    config(['mail.from.address' => 'default@example.com']);
    config(['mail.from.name' => 'Default Sender']);

    $mail = new OtpMail;
    $envelope = $mail->envelope();

    expect($envelope->from->address)->toBe('default@example.com');
    expect($envelope->from->name)->toBe('Default Sender');
});

it('WelcomeMail envelope uses config from and welcome_subject', function () {
    config(['frontdoor.mail.from.address' => 'noreply@myapp.com']);
    config(['frontdoor.mail.from.name' => 'My App']);
    config(['frontdoor.mail.welcome_subject' => 'Welcome aboard!']);

    $mail = new WelcomeMail;
    $envelope = $mail->envelope();

    expect($envelope->subject)->toBe('Welcome aboard!');
    expect($envelope->from->address)->toBe('noreply@myapp.com');
    expect($envelope->from->name)->toBe('My App');
});

it('WelcomeMail content passes account and appName', function () {
    config(['app.name' => 'TestApp']);

    $account = new SimpleAccountData(id: '1', name: 'Jane', email: 'jane@example.com');

    $mail = new WelcomeMail;
    $mail->setAccount($account);

    $content = $mail->content();

    expect($content->markdown)->toBe('frontdoor::mail.welcome');
    expect($content->with)->toBe([
        'account' => $account,
        'appName' => 'TestApp',
    ]);
});

it('WelcomeMail falls back to mail.from config when frontdoor config is null', function () {
    config(['frontdoor.mail.from.address' => null]);
    config(['frontdoor.mail.from.name' => null]);
    config(['mail.from.address' => 'default@example.com']);
    config(['mail.from.name' => 'Default Sender']);

    $mail = new WelcomeMail;
    $envelope = $mail->envelope();

    expect($envelope->from->address)->toBe('default@example.com');
    expect($envelope->from->name)->toBe('Default Sender');
});
