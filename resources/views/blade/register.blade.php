<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Verify your email</h1>
                <p class="mt-2 text-sm text-gray-600">We'll send a verification code to confirm your email address before creating your account.</p>
            </div>

            <form method="POST" action="{{ route('frontdoor.register') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ $email }}"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-red-300 @enderror"
                    >
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Send verification code
                </button>
            </form>

            <div class="mt-4 text-sm">
                <a href="{{ route('frontdoor.login') }}" class="text-indigo-600 hover:text-indigo-500">
                    &larr; {{ __('frontdoor::frontdoor.register_back') }}
                </a>
            </div>
        </div>
    </div>
</body>
</html>
