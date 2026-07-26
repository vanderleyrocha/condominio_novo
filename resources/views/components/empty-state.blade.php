@props(['message' => 'Nenhum registro encontrado.'])

<div {{ $attributes->merge(['class' => 'card py-10 text-center']) }}>
    <p class="text-sm text-slate-500">{{ $message }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
