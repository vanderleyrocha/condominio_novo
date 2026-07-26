@props(['label' => null, 'name' => null, 'help' => null])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id') ?? $name;
@endphp

<div>
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif class="label">{{ $label }}</label>
    @endif
    <input @if ($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'input', 'type' => 'text']) }}>
    @if ($name && isset($errors) && $errors->first($name))
        <p class="error-text">{{ $errors->first($name) }}</p>
    @endif
    @if ($help)
        <p class="help-text">{{ $help }}</p>
    @endif
</div>
