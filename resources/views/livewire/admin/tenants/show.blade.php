<div class="admin-page">
    <div class="admin-page-inner">
        @if (! $tenantProfilesAvailable)
            <x-admin.panel>
                <x-admin.empty-state
                    title="Tenant detail data is not available yet in this environment."
                    copy="This detail page will populate automatically once the tenant profile table is available."
                />
            </x-admin.panel>
        @else
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.95fr)]">
                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Account summary</h3>
                                <p class="mt-1 text-sm text-slate-600">Review the tenant account details and the profile information currently available on file.</p>
                            </div>

                            <dl class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                                <div><dt class="font-medium text-slate-500">Name</dt><dd class="mt-1">{{ $tenantProfile->user?->name }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Email</dt><dd class="mt-1">{{ $tenantProfile->user?->email }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Phone</dt><dd class="mt-1">{{ $tenantProfile->user?->phone ?: ($tenantProfile->phone ?: 'Not provided') }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Joined</dt><dd class="mt-1">{{ $tenantProfile->user?->created_at?->toFormattedDateString() ?: 'Not available' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Occupation</dt><dd class="mt-1">{{ $tenantProfile->occupation ?: 'Not provided' }}</dd></div>
                                <div><dt class="font-medium text-slate-500">Gender</dt><dd class="mt-1">{{ $tenantProfile->gender ?: 'Not provided' }}</dd></div>
                                <div class="md:col-span-2"><dt class="font-medium text-slate-500">Address</dt><dd class="mt-1">{{ $tenantProfile->address ?: 'Not provided' }}</dd></div>
                            </dl>
                        </div>
                    </x-admin.panel>
                </div>

                <div class="space-y-6">
                    <x-admin.panel>
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-950">Inspection request summary</h3>
                                <p class="mt-1 text-sm text-slate-600">Inspection activity for this tenant appears here when inspection coordination data is available.</p>
                            </div>

                            @if (! $inspectionRequestsAvailable)
                                <x-admin.empty-state
                                    title="Inspection request data is not available yet in this environment."
                                    copy="This section will populate automatically once the inspection request table is available."
                                />
                            @else
                                <div class="admin-data-box">
                                    <p class="text-sm font-medium text-slate-900">Total inspection requests</p>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{{ $inspectionRequestCount }}</p>
                                </div>

                                <div class="space-y-3">
                                    @forelse ($inspectionRequests as $inspectionRequest)
                                        <div class="admin-data-box">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="font-medium text-slate-900">{{ $inspectionRequest->property?->title ?: 'Property unavailable' }}</p>
                                                    <p class="mt-1 text-sm text-slate-600">{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</p>
                                                </div>
                                                <x-admin.badge>{{ str($inspectionRequest->status)->headline() }}</x-admin.badge>
                                            </div>
                                            <p class="mt-2 text-sm text-slate-700">
                                                Preferred visit:
                                                {{ $inspectionRequest->preferred_date?->toFormattedDateString() ?: 'No date provided' }}
                                            </p>
                                        </div>
                                    @empty
                                        <x-admin.empty-state
                                            title="This tenant has no inspection requests yet."
                                            copy="Any inspection activity tied to this tenant will appear here automatically."
                                        />
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    </x-admin.panel>
                </div>
            </div>
        @endif
    </div>
</div>
