@foreach($fields as $field)
    @php
        $fieldName = $field['name'];
        $fieldType = $field['type'];
        $fieldLabel = $field['label'];
        $fieldRequired = $field['required'];
        $fieldOptions = $field['options'] ?? [];
        $wireModel = $wirePrefix . '.' . $fieldName;
    @endphp

    <div>
        @if($fieldType === 'checkbox')
            <label class="flex items-center gap-2">
                <input
                    wire:model="{{ $wireModel }}"
                    type="checkbox"
                    id="{{ $fieldName }}"
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
                    wire:model="{{ $wireModel }}"
                    id="{{ $fieldName }}"
                    @if($fieldRequired) required @endif
                    rows="3"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error($fieldName) border-red-300 @enderror"
                ></textarea>
            @elseif($fieldType === 'select')
                <select
                    wire:model="{{ $wireModel }}"
                    id="{{ $fieldName }}"
                    @if($fieldRequired) required @endif
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error($fieldName) border-red-300 @enderror"
                >
                    <option value="">-- Select --</option>
                    @foreach($fieldOptions as $value => $optionLabel)
                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                    @endforeach
                </select>
            @else
                <input
                    wire:model="{{ $wireModel }}"
                    type="{{ $fieldType }}"
                    id="{{ $fieldName }}"
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
