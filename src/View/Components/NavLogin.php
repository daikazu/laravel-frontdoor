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
        /** @var string $loginLabel */
        $loginLabel = config('frontdoor.ui.nav.login_label', 'Login');
        $this->label ??= $loginLabel;

        /** @var string|null $accountRouteConfig */
        $accountRouteConfig = config('frontdoor.ui.nav.account_route');
        $this->accountRoute ??= $accountRouteConfig;
    }

    public function render(): View
    {
        return view('frontdoor::components.nav-login');
    }
}
