<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\View\Components;

use Daikazu\LaravelFrontdoor\Support\Avatar as AvatarHelper;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Avatar extends Component
{
    public string $initial;

    public string $gradient;

    public string $textColor;

    public string $sizeClasses;

    /** @var array<string, string> */
    protected array $sizes = [
        'xs' => 'w-6 h-6 text-xs',
        'sm' => 'w-8 h-8 text-sm',
        'md' => 'w-10 h-10 text-base',
        'lg' => 'w-12 h-12 text-lg',
        'xl' => 'w-16 h-16 text-xl',
    ];

    public function __construct(
        public string $identifier,
        public ?string $name = null,
        public string $size = 'md',
    ) {
        $avatar = AvatarHelper::make($identifier, $name);

        $this->initial = $avatar->initial;
        $this->gradient = $avatar->style->gradient;
        $this->textColor = $avatar->style->textColor;
        $this->sizeClasses = $this->sizes[$size] ?? $this->sizes['md'];
    }

    public function render(): View
    {
        return view('frontdoor::components.avatar');
    }
}
