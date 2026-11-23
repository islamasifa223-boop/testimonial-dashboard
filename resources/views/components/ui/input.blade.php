@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'autocomplete' => null, 'required' => false, 'bag' => 'default'])

@php
    $hasError = $errors->hasBag($bag) && $errors->getBag($bag)->has($name);

    // The error message is given an id of its own so that the input can
    // point at it. A screen reader then reads the message out when the
    // field is focused, and the field is marked as invalid.
    $errorId = $name.'-error';
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-ink">
            {{ $label }}
            @if ($required)
                <span class="text-red-600" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border-0 bg-surface px-3 py-2 text-sm text-ink shadow-sm ring-1 ring-inset ring-border-subtle placeholder:text-ink-subtle focus:ring-2 focus:ring-inset focus:ring-brand-500 '.($hasError ? 'ring-red-400' : '')]) }}
    >

    @if ($hasError)
        <p id="{{ $errorId }}" class="text-xs text-red-600">{{ $errors->getBag($bag)->first($name) }}</p>
    @endif
</div>
