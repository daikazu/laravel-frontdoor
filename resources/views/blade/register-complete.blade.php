<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('frontdoor::frontdoor.register_title') }} - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ __('frontdoor::frontdoor.register_title') }}</h1>
                <p class="mt-2 text-sm text-gray-600">{{ __('frontdoor::frontdoor.register_subtitle') }}</p>
            </div>

            <form method="POST" action="{{ route('frontdoor.register-complete') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email address</label>
                    <p class="mt-1 block w-full rounded-md bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-700">{{ $email }}</p>
                </div>

                @foreach($fields as $field)
                    @php
                        $fieldName = $field->name;
                        $fieldType = $field->type;
                        $fieldLabel = $field->label;
                        $fieldRequired = $field->required;
                        $fieldOptions = $field->options;
                    @endphp

                    <div>
                        @if($fieldType === 'checkbox')
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="{{ $fieldName }}"
                                    id="{{ $fieldName }}"
                                    value="1"
                                    {{ old($fieldName) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                >
                                <span class="text-sm font-medium text-gray-700">{{ $fieldLabel }}</span>
                            </label>
                        @else
                            <label for="{{ $fieldName }}" class="block text-sm font-medium text-gray-700">
                                {{ $fieldLabel }}
                                @if($fieldRequired)
                                    <span class="text-red-500">*</span>
                                @endif
                            </label>

                            @if($fieldType === 'textarea')
                                <textarea
                                    name="{{ $fieldName }}"
                                    id="{{ $fieldName }}"
                                    @if($fieldRequired) required @endif
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error($fieldName) border-red-300 @enderror"
                                >{{ old($fieldName) }}</textarea>
                            @elseif($fieldType === 'select')
                                <select
                                    name="{{ $fieldName }}"
                                    id="{{ $fieldName }}"
                                    @if($fieldRequired) required @endif
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error($fieldName) border-red-300 @enderror"
                                >
                                    <option value="">-- Select --</option>
                                    @foreach($fieldOptions as $value => $optionLabel)
                                        <option value="{{ $value }}" {{ old($fieldName) === (string) $value ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input
                                    type="{{ $fieldType }}"
                                    name="{{ $fieldName }}"
                                    id="{{ $fieldName }}"
                                    value="{{ old($fieldName) }}"
                                    @if($fieldRequired) required @endif
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error($fieldName) border-red-300 @enderror"
                                >
                            @endif
                        @endif

                        @error($fieldName)
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit" class="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('frontdoor::frontdoor.register_submit') }}
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
