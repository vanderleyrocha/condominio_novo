@props(['variant' => 'primary', 'size' => null, 'href' => null])

@php
    $classes = 'btn btn-' . $variant . ($size === 'sm' ? ' btn-sm' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
