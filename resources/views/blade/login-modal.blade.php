<div class="space-y-6">
    <div class="text-center">
        <h3 class="text-lg font-semibold text-gray-900">Sign in to your account</h3>
        <p class="mt-2 text-sm text-gray-600">Enter your email to receive a login code</p>
    </div>

    <form method="POST" action="{{ route('frontdoor.send-otp') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_redirect" value="{{ url()->current() }}">

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
            <input
                type="email"
                name="email"
                id="email"
                required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="you@example.com"
            >
        </div>

        @if(session('frontdoor_registration_available'))
            <div class="rounded-md bg-blue-50 p-4">
                <p class="text-sm text-blue-700">No account found. Would you like to create one?</p>
            </div>
        @endif

        <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
            Continue
        </button>
    </form>

    @if(session('frontdoor_registration_available'))
        <div class="mt-4">
            <a
                href="{{ route('frontdoor.show-register', ['email' => old('email')]) }}"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
            >
                {{ __('frontdoor::frontdoor.register_button') }}
            </a>
        </div>
    @endif
</div>
