@php $loginSuccessName = session()->pull('frontdoor.login_success'); @endphp

@if($loginSuccessName)
    <template x-teleport="body">
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 4000)"
            class="fixed inset-0 z-50 flex items-center justify-center"
        >
            <div
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500/50"
                x-on:click="show = false"
            ></div>

            <div
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative rounded-lg bg-white px-6 py-8 shadow-xl ring-1 ring-black/5 text-center space-y-3"
            >
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-900">You're signed in!</h3>
                <p class="text-sm text-gray-600">Welcome back, {{ $loginSuccessName }}</p>
                <button
                    x-on:click="show = false"
                    type="button"
                    class="mt-2 inline-flex justify-center rounded-md bg-indigo-600 px-6 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Continue
                </button>
            </div>
        </div>
    </template>
@endif

@auth('frontdoor')
    @php $identity = auth('frontdoor')->user(); @endphp

    <div
        x-data="{ open: false }"
        x-on:click.outside="open = false"
        class="relative"
    >
        <button
            x-on:click="open = !open"
            type="button"
            class="flex items-center gap-2 rounded-full p-1 hover:bg-gray-100 transition"
        >
            <x-frontdoor::avatar
                :identifier="$identity->getEmail()"
                :name="$identity->getName()"
                :size="$size"
            />
            <span class="text-sm font-medium text-gray-700 max-w-[120px] truncate">
                {{ $identity->getName() }}
            </span>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            x-cloak
            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg ring-1 ring-black/5 py-1 z-50"
        >
            <div class="px-4 py-2 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-900 truncate">{{ $identity->getName() }}</p>
                <p class="text-xs text-gray-500 truncate">{{ $identity->getEmail() }}</p>
            </div>

            @if($accountRoute)
                <a href="{{ $accountRoute }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    {{ config('frontdoor.ui.nav.account_label', 'Account') }}
                </a>
            @endif

            <form method="POST" action="{{ route('frontdoor.logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    {{ config('frontdoor.ui.nav.logout_label', 'Logout') }}
                </button>
            </form>
        </div>
    </div>
@else
    <button
        x-data
        x-on:click="$dispatch('frontdoor-open')"
        type="button"
        {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition']) }}
    >
        {{ $label }}
    </button>

    <template x-teleport="body">
        <x-frontdoor::modal />
    </template>
@endauth
