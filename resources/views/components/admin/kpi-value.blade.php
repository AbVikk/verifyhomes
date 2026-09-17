@props([
    'value',
    'exact' => null,
])

<p
    {{ $attributes->class(['admin-kpi-value']) }}
    @if ($exact) title="{{ $exact }}" aria-label="Exact value: {{ $exact }}" @endif
>{{ $value }}</p>
