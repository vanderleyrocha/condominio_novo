@props(['label', 'icon' => null, 'tone' => 'brand'])

@php
    $tones = [
        'brand' => 'bg-brand-light text-brand',
        'success' => 'bg-emerald-50 text-emerald-600',
        'danger' => 'bg-red-50 text-red-600',
        'warning' => 'bg-amber-50 text-amber-600',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'card']) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="section-label">{{ $label }}</p>
        @if ($icon)
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}" aria-hidden="true">
                <x-icon :name="$icon" class="size-4" />
            </span>
        @endif
    </div>
    <div class="mt-3 text-2xl font-bold tabular-nums">{{ $slot }}</div>
    @isset($footer)
        <p class="mt-1.5 text-xs text-slate-500">{{ $footer }}</p>
    @endisset
</div>
