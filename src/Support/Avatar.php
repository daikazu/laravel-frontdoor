<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Support;

class Avatar
{
    public static function gradient(string $identifier): AvatarStyle
    {
        $hash = sha1(strtolower(trim($identifier)));

        $hue1 = hexdec(substr($hash, 0, 2)) / 255 * 360;
        $hue2 = hexdec(substr($hash, 2, 2)) / 255 * 360;
        $angle = hexdec(substr($hash, 4, 2)) / 255 * 360;

        $saturation = config('frontdoor.avatar.saturation', 65);
        $lightness = config('frontdoor.avatar.lightness', 55);

        // Ensure hues are different enough (min 30° apart)
        if (abs($hue1 - $hue2) < 30) {
            $hue2 = fmod($hue1 + 60, 360);
        }

        $color1 = sprintf('hsl(%d, %d%%, %d%%)', (int) $hue1, $saturation, $lightness);
        $color2 = sprintf('hsl(%d, %d%%, %d%%)', (int) $hue2, $saturation, $lightness);

        $textColor = $lightness > 55 ? '#1f2937' : '#ffffff';

        return new AvatarStyle(
            gradient: sprintf('linear-gradient(%ddeg, %s, %s)', (int) $angle, $color1, $color2),
            textColor: $textColor,
            hue1: $hue1,
            hue2: $hue2,
        );
    }

    public static function initial(string $name): string
    {
        $name = trim($name);

        if (str_contains($name, '@')) {
            $name = explode('@', $name)[0];
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    public static function make(string $identifier, ?string $name = null): AvatarData
    {
        $style = static::gradient($identifier);
        $initial = static::initial($name ?? $identifier);

        return new AvatarData(
            initial: $initial,
            style: $style,
            identifier: $identifier,
        );
    }
}
