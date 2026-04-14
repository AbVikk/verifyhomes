@props([
    'title',
    'description',
    'histories',
    'emptyMessage' => 'No status history yet.',
    'fallbackChangedBy' => null,
])

<x-admin.panel>
    <div class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-950">{{ $title }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
        </div>

        <div class="space-y-3">
            @forelse ($histories as $history)
                <div class="admin-data-box">
                    <div class="flex items-center justify-between gap-4">
                        <p class="font-medium text-slate-950">{{ str($history->from_status ?: 'not_set')->headline() }} to {{ str($history->to_status)->headline() }}</p>
                        <p class="text-xs text-slate-500">{{ $history->created_at->diffForHumans() }}</p>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">Changed by {{ $history->changedBy?->name ?: $fallbackChangedBy }}</p>
                    @if ($history->notes)
                        <p class="mt-2 text-sm text-slate-700">{{ $history->notes }}</p>
                    @endif
                </div>
            @empty
                <x-admin.empty-state :title="$emptyMessage" />
            @endforelse
        </div>
    </div>
</x-admin.panel>
