@php
    use App\Support\ReviewStatusOptions;
@endphp

<div class="admin-page">
    <div class="admin-page-inner">
        <x-admin.panel>
            <div class="space-y-4">
                @if (session('status'))
                    <x-admin.alert :tone="session('statusTone', 'success')">
                        {{ session('status') }}
                    </x-admin.alert>
                @endif

                <div class="space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">Landlord verification queue</h3>
                        <p class="mt-1 text-sm text-slate-600">Pending and under-review profiles stay at the top for faster review.</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,0.65fr)]">
                        <div>
                            <x-admin.label for="search">Search landlords</x-admin.label>
                            <x-admin.input
                                wire:model.live.debounce.300ms="search"
                                id="search"
                                type="search"
                                placeholder="Search name, email, phone, or business name"
                            />
                        </div>

                        <div>
                            <x-admin.label for="statusFilter">Filter by status</x-admin.label>
                            <x-admin.select wire:model.live="statusFilter" id="statusFilter">
                                <option value="all">All statuses</option>
                                @foreach (ReviewStatusOptions::landlordStatuses() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-admin.select>
                        </div>
                    </div>
                </div>

                <div class="admin-bulk-bar">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="admin-bulk-count">{{ count($selectedLandlordIds) }} selected</span>
                        <x-admin.button wire:click="bulkApprove" variant="success" size="sm" :disabled="count($selectedLandlordIds) === 0">
                            Mark Approved
                        </x-admin.button>
                        <x-admin.button wire:click="bulkReject" variant="danger" size="sm" :disabled="count($selectedLandlordIds) === 0">
                            Mark Rejected
                        </x-admin.button>
                        <x-admin.button wire:click="bulkMarkUnderReview" variant="secondary" size="sm" :disabled="count($selectedLandlordIds) === 0">
                            Mark Under Review
                        </x-admin.button>
                    </div>

                    <x-admin.button wire:click="clearSelection" variant="quiet" size="sm" :disabled="count($selectedLandlordIds) === 0">
                        Clear Selection
                    </x-admin.button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="admin-table-head">
                            <tr>
                                <th class="admin-table-head-cell w-14">
                                    <label class="inline-flex items-center justify-center">
                                        <span class="sr-only">Select all landlords on this page</span>
                                        <input
                                            type="checkbox"
                                            class="admin-checkbox"
                                            wire:model.live="selectPage"
                                        />
                                    </label>
                                </th>
                                <th class="admin-table-head-cell">Landlord</th>
                                <th class="admin-table-head-cell">Contact</th>
                                <th class="admin-table-head-cell">Status</th>
                                <th class="admin-table-head-cell">Documents</th>
                                <th class="admin-table-head-cell"></th>
                            </tr>
                        </thead>
                        <tbody class="admin-table-body">
                            @forelse ($landlords as $landlord)
                                <tr>
                                    <td class="px-4 py-4 align-top">
                                        <label class="inline-flex items-center justify-center">
                                            <span class="sr-only">Select {{ $landlord->user?->name }}</span>
                                            <input
                                                type="checkbox"
                                                class="admin-checkbox"
                                                wire:model.live="selectedLandlordIds"
                                                value="{{ $landlord->id }}"
                                            />
                                        </label>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-medium text-slate-900">{{ $landlord->user?->name }}</div>
                                        <div class="text-sm text-slate-500">{{ $landlord->business_name ?: 'No display name yet' }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-600">
                                        <div>{{ $landlord->user?->email }}</div>
                                        <div>{{ $landlord->user?->phone ?: 'No phone yet' }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-admin.badge>
                                            {{ str($landlord->verification_status)->headline() }}
                                        </x-admin.badge>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-600">{{ $landlord->documents_count }}</td>
                                    <td class="px-4 py-4 text-right">
                                        <x-admin.action-link href="{{ route('admin.landlords.show', $landlord) }}">Open Review</x-admin.action-link>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8">
                                        <x-admin.empty-state
                                            title="No landlord profiles match the current search or filters."
                                            copy="Try a broader search term or reset the verification status filter."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $landlords->links() }}
            </div>
        </x-admin.panel>
    </div>
</div>
