@props([])

<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="table-modern">
        @isset($head)
            <thead>{{ $head }}</thead>
        @endisset
        <tbody>{{ $slot }}</tbody>
        @isset($foot)
            <tfoot>{{ $foot }}</tfoot>
        @endisset
    </table>
</div>
