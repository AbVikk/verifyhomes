@props([
    'tone' => 'info',
    'label' => null,
    'title',
    'copy' => null,
    'actionUrl' => null,
    'actionLabel' => null,
])

<section {{ $attributes->merge(['class' => "admin-workflow-state admin-workflow-state-{$tone}"]) }}>
    @if ($label)
        <p class="admin-workflow-state-label">{{ $label }}</p>
    @endif

    <h3 class="admin-workflow-state-title">{{ $title }}</h3>

    @if ($copy)
        <p class="admin-workflow-state-copy">{{ $copy }}</p>
    @endif

    {{ $slot }}

    @if ($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="admin-workflow-state-action-link">{{ $actionLabel }}</a>
    @endif
</section>
