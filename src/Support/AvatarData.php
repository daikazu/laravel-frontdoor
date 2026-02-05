<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\Support;

readonly class AvatarData
{
    public function __construct(
        public string $initial,
        public AvatarStyle $style,
        public string $identifier,
    ) {}
}
