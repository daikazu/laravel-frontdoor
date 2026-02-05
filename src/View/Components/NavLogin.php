<?php

declare(strict_types=1);

namespace Daikazu\LaravelFrontdoor\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class NavLogin extends Component
{
    public function __construct(
        public ?string $label = null,
        public ?string $accountRoute = null,
        public string $size = 'md',
    ) {
        $this->label ??= config('frontdoor.ui.nav.login_label', 'Login');
        $this->accountRoute ??= config('frontdoor.ui.nav.account_route');
    }

    public function render(): View
    {
        return view('frontdoor::components.nav-login');
    }
}
