@props(['route', 'label'])
@php($ativo = request()->routeIs($route) || request()->routeIs(str_replace('.index', '.*', $route)))
<a href="{{ route($route) }}"
   {{ $attributes->class([
        'block rounded-md px-3 py-2 transition',
        'bg-brand/20 text-white font-medium' => $ativo,
        'text-gray-300 hover:bg-gray-800 hover:text-white' => ! $ativo,
   ]) }}>
    {{ $label }}
</a>
