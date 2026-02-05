<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Support;

readonly class AvatarStyle
{
    public function __construct(
        public string $gradient,
        public string $textColor,
        public float $hue1,
        public float $hue2,
    ) {}

    public function backgroundStyle(): string
    {
        return "background: {$this->gradient};";
    }

    public function textStyle(): string
    {
        return "color: {$this->textColor};";
    }

    public function toStyle(): string
    {
        return $this->backgroundStyle().' '.$this->textStyle();
    }
}
