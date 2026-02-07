<?php

use Daikazu\LaravelFrontdoor\Support\AvatarStyle;

it('backgroundStyle returns background CSS', function () {
    $style = new AvatarStyle(
        gradient: 'linear-gradient(45deg, red, blue)',
        textColor: '#ffffff',
        hue1: 0.0,
        hue2: 240.0,
    );

    expect($style->backgroundStyle())->toBe('background: linear-gradient(45deg, red, blue);');
});

it('textStyle returns color CSS', function () {
    $style = new AvatarStyle(
        gradient: 'linear-gradient(45deg, red, blue)',
        textColor: '#1f2937',
        hue1: 0.0,
        hue2: 240.0,
    );

    expect($style->textStyle())->toBe('color: #1f2937;');
});

it('toStyle combines background and text styles', function () {
    $style = new AvatarStyle(
        gradient: 'linear-gradient(90deg, green, yellow)',
        textColor: '#ffffff',
        hue1: 120.0,
        hue2: 60.0,
    );

    expect($style->toStyle())->toBe('background: linear-gradient(90deg, green, yellow); color: #ffffff;');
});
