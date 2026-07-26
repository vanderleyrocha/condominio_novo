@props(['route', 'label'])
@php($ativo = request()->routeIs($route) || request()->routeIs(str_replace('.index', '.*', $route)))
<a href="{{ route($route) }}"
   @if ($ativo) aria-current="page" @endif
   {{ $attributes->class([
        'block rounded-lg px-3 py-2 transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand',
        'bg-brand font-medium text-white shadow-sm' => $ativo,
        'text-slate-300 hover:bg-slate-800 hover:text-white' => ! $ativo,
   ]) }}>
    {{ $label }}
</a>
