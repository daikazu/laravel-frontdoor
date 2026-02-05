<div class="space-y-6">
    @if($step === 'email')
        <div class="text-center">
            <h3 class="text-lg font-semibold text-gray-900">Sign in to your account</h3>
            <p class="mt-2 text-sm text-gray-600">Enter your email to receive a login code</p>
        </div>

        <form wire:submit="submitEmail" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <input
                    wire:model="email"
                    type="email"
                    id="email"
                    autocomplete="email"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-300 @enderror"
                    placeholder="you@example.com"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if($errorMessage)
                <div class="rounded-md bg-red-50 p-4">
                    <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                </div>
            @endif

            @if($showRegistrationPrompt)
                <div class="rounded-md bg-blue-50 p-4">
                    <p class="text-sm text-blue-700">No account found. Would you like to create one?</p>
                    <button
                        wire:click="register"
                        wire:loading.attr="disabled"
                        type="button"
                        class="mt-2 w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="register">Create account</span>
                        <span wire:loading wire:target="register">Sending verification...</span>
                    </button>
                </div>
            @else
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                    <span wire:loading.remove>Continue</span>
                    <span wire:loading>Sending...</span>
                </button>
            @endif
        </form>

    @elseif($step === 'register')
        <div class="text-center">
            <h3 class="text-lg font-semibold text-gray-900">{{ __('frontdoor::frontdoor.register_title') }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ __('frontdoor::frontdoor.register_subtitle') }}</p>
        </div>

        <form wire:submit="submitRegistration" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email address</label>
                <p class="mt-1 block w-full rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-700">{{ $email }}</p>
            </div>

            @include('frontdoor::livewire.register-fields', [
                'fields' => $registrationFields,
                'wirePrefix' => 'registrationData',
            ])

            @if($errorMessage)
                <div class="rounded-md bg-red-50 p-4">
                    <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                </div>
            @endif

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="submitRegistration">{{ __('frontdoor::frontdoor.register_submit') }}</span>
                <span wire:loading wire:target="submitRegistration">{{ __('frontdoor::frontdoor.registering') }}</span>
            </button>
        </form>

        <div class="text-sm">
            <button
                wire:click="goBack"
                type="button"
                class="text-indigo-600 hover:text-indigo-500"
            >
                &larr; {{ __('frontdoor::frontdoor.register_back') }}
            </button>
        </div>

    @elseif($step === 'otp')
        <div class="text-center">
            @if($isRegistering)
                <h3 class="text-lg font-semibold text-gray-900">Verify your email</h3>
                <p class="mt-2 text-sm text-gray-600">We sent a verification code to <strong>{{ $email }}</strong></p>
            @else
                <h3 class="text-lg font-semibold text-gray-900">Enter your code</h3>
                <p class="mt-2 text-sm text-gray-600">We sent a 6-digit code to <strong>{{ $email }}</strong></p>
            @endif
        </div>

        <form wire:submit="submitCode" class="space-y-4">
            <div>
                <label for="code" class="block text-sm font-medium text-gray-700">Verification code</label>
                <input
                    wire:model="code"
                    type="text"
                    id="code"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="6"
                    autocomplete="one-time-code"
                    required
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-center text-2xl tracking-widest @error('code') border-red-300 @enderror"
                    placeholder="000000"
                >
                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if($errorMessage)
                <div class="rounded-md bg-red-50 p-4">
                    <p class="text-sm text-red-700">{{ $errorMessage }}</p>
                </div>
            @endif

            <button
                type="submit"
                wire:loading.attr="disabled"
                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
                <span wire:loading.remove>Verify</span>
                <span wire:loading>Verifying...</span>
            </button>
        </form>

        <div class="flex items-center justify-between text-sm">
            <button
                wire:click="goBack"
                type="button"
                class="text-indigo-600 hover:text-indigo-500"
            >
                &larr; Use different email
            </button>
            <button
                wire:click="resendCode"
                type="button"
                class="text-indigo-600 hover:text-indigo-500"
            >
                Resend code
            </button>
        </div>

    @endif
</div>
