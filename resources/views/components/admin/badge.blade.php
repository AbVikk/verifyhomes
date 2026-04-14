@props([
    'tone' => 'neutral',
])

<span {{ $attributes->merge(['class' => "admin-badge admin-badge-{$tone}"]) }}>
    {{ $slot }}
</span>
