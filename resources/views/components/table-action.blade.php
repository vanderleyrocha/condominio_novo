@props(['variant' => null, 'href' => null])

@php
    $classes = match ($variant) {
        'muted' => 'table-action-muted',
        'danger' => 'table-action-danger',
        default => 'table-action',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
