<div class="admin-page">
    <div class="admin-page-inner">
        <x-admin.panel>
            <div class="space-y-4">
                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">Tenant directory</h3>
                        <p class="mt-1 text-sm text-slate-600">Review tenant accounts and open each profile for a closer inspection of account details and request activity.</p>
                    </div>

                    <div class="max-w-xl">
                        <x-admin.label for="search">Search tenants</x-admin.label>
                        <x-admin.input
                            wire:model.live.debounce.300ms="search"
                            id="search"
                            type="search"
                            placeholder="Search tenant name or email"
                        />
                    </div>
                </div>

                @if (! $tenantProfilesAvailable)
                    <x-admin.empty-state
                        title="Tenant data is not available yet in this environment."
                        copy="This page will populate automatically once the tenant profile table is available."
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Tenant</th>
                                    <th class="admin-table-head-cell">Contact</th>
                                    <th class="admin-table-head-cell">Joined</th>
                                    <th class="admin-table-head-cell">Inspection Requests</th>
                                    <th class="admin-table-head-cell"></th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($tenantProfiles as $tenantProfile)
                                    <tr>
                                        <td class="px-4 py-4">
                                            <div class="font-medium text-slate-900">{{ $tenantProfile->user?->name }}</div>
                                            <div class="text-sm text-slate-500">{{ $tenantProfile->occupation ?: 'No occupation provided' }}</div>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            <div>{{ $tenantProfile->user?->email }}</div>
                                            <div>{{ $tenantProfile->user?->phone ?: ($tenantProfile->phone ?: 'No phone yet') }}</div>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            {{ $tenantProfile->user?->created_at?->toFormattedDateString() ?: 'Not available' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            @if ($inspectionRequestsAvailable)
                                                {{ $tenantProfile->inspection_requests_count }}
                                            @else
                                                Inspection data unavailable
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-right">
                                            <x-admin.action-link href="{{ route('admin.tenants.show', ['tenantProfileId' => $tenantProfile->getKey()]) }}">Open Tenant</x-admin.action-link>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8">
                                            <x-admin.empty-state
                                                title="No tenant profiles match the current search."
                                                copy="Try a broader tenant name or email search."
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $tenantProfiles->links() }}
                @endif
            </div>
        </x-admin.panel>
    </div>
</div>
