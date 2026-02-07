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

it('adjusts hue2 when hues are less than 30 degrees apart', function () {
    // We need to find an identifier whose SHA1 produces hues < 30° apart.
    // Brute-force check a few known cases.
    $found = false;
    foreach (['close-hues-test-xyz', 'abc123', 'zz', 'qwerty', 'aaa', 'bbb', 'test1', 'hue-test'] as $candidate) {
        $hash = sha1(strtolower(trim($candidate)));
        $hue1 = hexdec(substr($hash, 0, 2)) / 255 * 360;
        $hue2 = hexdec(substr($hash, 2, 2)) / 255 * 360;

        if (abs($hue1 - $hue2) < 30) {
            $style = Avatar::gradient($candidate);

            // After adjustment, hue2 should be hue1 + 60 (mod 360)
            $expectedHue2 = fmod($hue1 + 60, 360);
            expect(round($style->hue2, 2))->toBe(round($expectedHue2, 2));
            $found = true;
            break;
        }
    }

    // If none of the above work, generate one algorithmically
    if (! $found) {
        for ($i = 0; $i < 1000; $i++) {
            $candidate = "huetest{$i}";
            $hash = sha1(strtolower(trim($candidate)));
            $hue1 = hexdec(substr($hash, 0, 2)) / 255 * 360;
            $hue2 = hexdec(substr($hash, 2, 2)) / 255 * 360;

            if (abs($hue1 - $hue2) < 30) {
                $style = Avatar::gradient($candidate);
                $expectedHue2 = fmod($hue1 + 60, 360);
                expect(round($style->hue2, 2))->toBe(round($expectedHue2, 2));
                $found = true;
                break;
            }
        }
    }

    expect($found)->toBeTrue();
});
