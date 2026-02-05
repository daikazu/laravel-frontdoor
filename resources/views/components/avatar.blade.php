<div
    {{ $attributes->merge(['class' => "{$sizeClasses} rounded-full flex items-center justify-center font-semibold select-none"]) }}
    style="background: {{ $gradient }}; color: {{ $textColor }};"
    title="{{ $name ?? $identifier }}"
>
    {{ $initial }}
</div>
