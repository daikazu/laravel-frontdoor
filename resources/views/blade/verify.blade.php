<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Code - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="text-center mb-6">
                @if($isRegistering ?? false)
                    <h1 class="text-2xl font-bold text-gray-900">Verify your email</h1>
                    <p class="mt-2 text-sm text-gray-600">We sent a verification code to <strong>{{ $email }}</strong></p>
                @else
                    <h1 class="text-2xl font-bold text-gray-900">Enter your code</h1>
                    <p class="mt-2 text-sm text-gray-600">We sent a 6-digit code to <strong>{{ $email }}</strong></p>
                @endif
            </div>

            <form method="POST" action="{{ route('frontdoor.verify-otp') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Verification code</label>
                    <input
                        type="text"
                        name="code"
                        id="code"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        autocomplete="one-time-code"
                        required
                        autofocus
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center text-2xl tracking-widest @error('code') border-red-300 @enderror"
                        placeholder="000000"
                    >
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Verify
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="{{ route('frontdoor.login') }}" class="text-sm text-indigo-600 hover:text-indigo-500">
                    &larr; Use different email
                </a>
            </div>
        </div>
    </div>
</body>
</html>
