<?php

use Daikazu\LaravelFrontdoor\Support\Avatar;

it('generates consistent gradient for same identifier', function () {
    $style1 = Avatar::gradient('jane@example.com');
    $style2 = Avatar::gradient('jane@example.com');

    expect($style1->gradient)->toBe($style2->gradient);
    expect($style1->textColor)->toBe($style2->textColor);
});

it('generates different gradients for different identifiers', function () {
    $style1 = Avatar::gradient('jane@example.com');
    $style2 = Avatar::gradient('bob@example.com');

    expect($style1->gradient)->not->toBe($style2->gradient);
});

it('normalizes email case for consistency', function () {
    $style1 = Avatar::gradient('Jane@Example.com');
    $style2 = Avatar::gradient('jane@example.com');

    expect($style1->gradient)->toBe($style2->gradient);
});

it('extracts initial from name', function () {
    expect(Avatar::initial('Jane Doe'))->toBe('J');
    expect(Avatar::initial('bob'))->toBe('B');
});

it('extracts initial from email', function () {
    expect(Avatar::initial('jane@example.com'))->toBe('J');
});

it('handles unicode names', function () {
    expect(Avatar::initial('李明'))->toBe('李');
});

it('makes complete avatar data', function () {
    $avatar = Avatar::make('jane@example.com', 'Jane Doe');

    expect($avatar->initial)->toBe('J');
    expect($avatar->identifier)->toBe('jane@example.com');
    expect($avatar->style->gradient)->toContain('linear-gradient');
});

it('generates valid CSS gradient string', function () {
    $style = Avatar::gradient('test@example.com');

    expect($style->gradient)->toContain('linear-gradient');
    expect($style->gradient)->toContain('hsl');
    expect($style->gradient)->toContain('deg');
});

it('returns proper text color for contrast', function () {
    $style = Avatar::gradient('test@example.com');

    expect($style->textColor)->toBeIn(['#1f2937', '#ffffff']);
});
