<div class="admin-page">
    <div class="admin-page-inner space-y-6">
        <x-admin.panel>
            <div class="space-y-2">
                <p class="admin-eyebrow">Notifications</p>
                <h2 class="admin-panel-title">Landlord updates</h2>
                <p class="admin-panel-copy">Paid property money, rent reminders, and tenant updates appear here.</p>
            </div>
        </x-admin.panel>

        @if (! $notificationsAvailable)
            <x-admin.empty-state
                title="Notifications are not available yet."
                copy="This page will populate once notifications are enabled in this environment."
            />
        @elseif ($notifications->isEmpty())
            <x-admin.empty-state
                title="No notifications yet."
                copy="We will notify you here when payments or coordination updates arrive."
            >
                <a href="{{ route('landlord.occupancy.index') }}" class="admin-inline-link mt-3 inline-flex">View occupants</a>
            </x-admin.empty-state>
        @else
            <div class="space-y-4">
                @foreach ($notifications as $notification)
                    <x-admin.panel>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">{{ $notification->title }}</p>
                                @if ($notification->body)
                                    <p class="mt-2 text-sm text-slate-600">{{ $notification->body }}</p>
                                @endif
                                <p class="mt-2 text-xs text-slate-500">{{ $notification->created_at?->diffForHumans() ?? 'Recently' }}</p>
                            </div>
                            <div class="flex flex-col items-start gap-2 sm:items-end">
                                <x-status-chip tone="{{ $notification->read_at ? 'neutral' : 'info' }}">
                                    {{ $notification->read_at ? 'Read' : 'Unread' }}
                                </x-status-chip>
                                @if ($notification->link)
                                    <a href="{{ $notification->link }}" class="admin-inline-link">Open</a>
                                @endif
                            </div>
                        </div>
                    </x-admin.panel>
                @endforeach
            </div>
        @endif
    </div>
</div>
