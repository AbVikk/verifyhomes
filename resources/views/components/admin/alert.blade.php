@props([
    'tone' => 'success',
])

<div {{ $attributes->merge(['class' => "admin-alert admin-alert-{$tone}"]) }}>
    {{ $slot }}
</div>
