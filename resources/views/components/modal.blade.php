@props(['title' => null, 'close' => null, 'maxWidth' => 'md'])

@php
    $widths = ['md' => 'max-w-md', 'lg' => 'max-w-lg', '2xl' => 'max-w-2xl', '3xl' => 'max-w-3xl'];
@endphp

<div class="modal-overlay"
     @if ($close) wire:keydown.escape.window="{{ $close }}" wire:click.self="{{ $close }}" @endif
     role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
    <div {{ $attributes->merge(['class' => 'modal-panel ' . ($widths[$maxWidth] ?? $widths['md'])]) }}>
        @if ($title)
            <h2 class="mb-4 text-lg font-semibold text-slate-900">{{ $title }}</h2>
        @endif
        {{ $slot }}
        @isset($footer)
            <div class="mt-6 flex flex-wrap justify-end gap-2">{{ $footer }}</div>
        @endisset
    </div>
</div>
