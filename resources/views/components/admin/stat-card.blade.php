@props([
    'label',
    'value',
    'icon' => 'dashboard',
    'note' => 'Snapshot',
    'trend' => [6, 8, 7, 11, 10, 13, 12],
])

@php
    $points = collect($trend)->values();
    $maxPoint = max($points->max() ?: 1, 1);
    $minPoint = $points->min() ?? 0;
    $range = max($maxPoint - $minPoint, 1);
    $stepX = $points->count() > 1 ? 100 / ($points->count() - 1) : 100;
    $sparkline = $points
        ->map(function ($point, $index) use ($minPoint, $range, $stepX) {
            $x = $index * $stepX;
            $y = 28 - ((($point - $minPoint) / $range) * 20);

            return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
        })
        ->implode(' ');
@endphp

<x-admin.panel padding="p-5" class="admin-stat-card h-full">
    <span class="admin-stat-accent" aria-hidden="true"></span>

    <div class="admin-stat-shell">
        <span class="admin-stat-icon" aria-hidden="true">
            @switch($icon)
                @case('landlords')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 8.25a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 18.75a5.25 5.25 0 0110.5 0" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.5a6.75 6.75 0 10-6 0" />
                    </svg>
                    @break
                @case('properties')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 21V7.5L12 3l6.75 4.5V21" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 9.75h.008v.008H9V9.75zm0 3.75h.008v.008H9V13.5zm0 3.75h.008v.008H9v-.008zm6-7.5h.008v.008H15V9.75zm0 3.75h.008v.008H15V13.5z" />
                    </svg>
                    @break
                @case('inspection')
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75V6m7.5-2.25V6M4.5 8.25h15" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 4.5h10.5A2.25 2.25 0 0119.5 6.75v10.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 17.25V6.75A2.25 2.25 0 016.75 4.5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 12h4.5M9.75 15h2.25" />
                    </svg>
                    @break
                @default
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75L12 3l8.25 6.75v9a2.25 2.25 0 01-2.25 2.25h-12A2.25 2.25 0 013.75 18.75v-9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 21V12.75h4.5V21" />
                    </svg>
            @endswitch
        </span>

        <div class="admin-stat-copy">
            <p class="admin-stat-label">{{ $label }}</p>
            <p class="admin-stat-value">{{ $value }}</p>
            <p class="admin-stat-note">
                <span class="admin-stat-dot" aria-hidden="true"></span>
                <span>{{ $note }}</span>
            </p>
        </div>
    </div>

    <svg class="admin-stat-sparkline" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
        <path class="admin-stat-sparkline-guide" d="M0 28 H100" />
        <path d="M {{ $sparkline }}" />
    </svg>
</x-admin.panel>
