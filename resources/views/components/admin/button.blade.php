@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'tag' => 'button',
])

@php
    $sizeClass = $size === 'sm' ? 'admin-button-sm' : '';
@endphp

@if ($tag === 'a')
    <a {{ $attributes->merge(['class' => trim("admin-button admin-button-{$variant} {$sizeClass}")]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => $type, 'class' => trim("admin-button admin-button-{$variant} {$sizeClass}")]) }}>
        {{ $slot }}
    </button>
@endif
