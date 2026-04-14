@props([
    'title',
    'copy' => null,
])

<div {{ $attributes->merge(['class' => 'admin-empty-state']) }}>
    <h4 class="admin-empty-state-title">{{ $title }}</h4>
    @if ($copy)
        <p class="admin-empty-state-copy">{{ $copy }}</p>
    @endif
    {{ $slot }}
</div>
