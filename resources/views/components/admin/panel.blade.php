@props([
    'padding' => 'p-6',
    'class' => '',
])

<section {{ $attributes->merge(['class' => "admin-surface {$class}"]) }}>
    <div class="admin-surface-content {{ $padding !== 'p-6' ? $padding : '' }}">
        {{ $slot }}
    </div>
</section>
