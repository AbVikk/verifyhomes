<div class="admin-page"><div class="admin-page-inner max-w-3xl">
    <x-admin.panel>
        <div class="space-y-6">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'tenant-profile-photo')" class="h-28 w-28 shrink-0 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500" aria-label="Enlarge {{ $tenant->name }} profile photo">
                    @if($tenant->avatar_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($tenant->avatar_path) }}" alt="{{ $tenant->name }} profile photo" class="h-full w-full object-cover">@else<div class="flex h-full w-full items-center justify-center text-2xl font-semibold text-slate-500">{{ str($tenant->name)->substr(0, 2)->upper() }}</div>@endif
                </button>
                <div><p class="admin-eyebrow">Tenant profile</p><h2 class="admin-panel-title">{{ $tenant->name }}</h2>
                    @if($tenant->tenantProfile?->isVerified())<x-status-chip tone="success">Verified by VerifyHomes</x-status-chip>@else<x-status-chip tone="warning">Not verified</x-status-chip>@endif
                    <p class="admin-panel-copy mt-3">VerifyHomes manages identity review and communication. Contact and verification details are kept private.</p>
                </div>
            </div>
            <div><h3 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Your property relationship</h3><div class="mt-3 space-y-3">
                @foreach($occupancies as $occupancy)<div class="admin-data-box flex items-center justify-between gap-4"><div><p class="font-medium text-slate-900">{{ $occupancy->property?->title }}</p><p class="mt-1 text-sm text-slate-600">{{ $occupancy->status === 'upcoming' ? 'Upcoming tenant' : ($occupancy->status === 'moved_out' ? 'Former tenant' : 'Current tenant') }}</p></div><x-status-chip tone="{{ $occupancy->status === 'upcoming' ? 'info' : ($occupancy->status === 'moved_out' ? 'neutral' : 'success') }}">{{ str($occupancy->status)->headline() }}</x-status-chip></div>@endforeach
            </div></div>
        </div>
    </x-admin.panel>
    <x-modal name="tenant-profile-photo"><div class="bg-slate-950 p-4"><div class="mb-3 flex justify-end"><button type="button" x-on:click="$dispatch('close-modal', 'tenant-profile-photo')" class="admin-button admin-button-secondary">Close</button></div>@if($tenant->avatar_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($tenant->avatar_path) }}" alt="{{ $tenant->name }} profile photo" class="max-h-[70vh] w-full object-contain">@else<div class="flex h-64 items-center justify-center text-5xl font-semibold text-slate-300">{{ str($tenant->name)->substr(0, 2)->upper() }}</div>@endif</div></x-modal>
</div></div>
