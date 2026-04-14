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
                            <h3 class="text-lg font-semibold text-slate-950">Property review queue</h3>
                            <p class="mt-1 text-sm text-slate-600">Pending property submissions stay at the top for review.</p>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,0.7fr)_minmax(0,0.7fr)]">
                            <div>
                                <x-admin.label for="search">Search properties</x-admin.label>
                                <x-admin.input
                                    wire:model.live.debounce.300ms="search"
                                    id="search"
                                    type="search"
                                    placeholder="Search title, city, area, landmark, or landlord"
                                />
                            </div>

                            <div>
                                <x-admin.label for="statusFilter">Review status</x-admin.label>
                                <x-admin.select wire:model.live="statusFilter" id="statusFilter">
                                    <option value="all">All statuses</option>
                                    @foreach (ReviewStatusOptions::propertyStatuses() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-admin.select>
                            </div>

                            <div>
                                <x-admin.label for="publishFilter">Publish state</x-admin.label>
                                <x-admin.select wire:model.live="publishFilter" id="publishFilter">
                                    <option value="all">All publish states</option>
                                    <option value="published">Published</option>
                                    <option value="approved_unpublished">Approved, unpublished</option>
                                    <option value="not_eligible">Not eligible</option>
                                </x-admin.select>
                            </div>
                        </div>
                    </div>

                    <div class="admin-bulk-bar">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="admin-bulk-count">{{ count($selectedPropertyIds) }} selected</span>
                            <x-admin.button wire:click="bulkApprove" variant="success" size="sm" :disabled="count($selectedPropertyIds) === 0">
                                Mark Approved
                            </x-admin.button>
                            <x-admin.button wire:click="bulkReject" variant="danger" size="sm" :disabled="count($selectedPropertyIds) === 0">
                                Mark Rejected
                            </x-admin.button>
                            <x-admin.button wire:click="bulkUnpublish" variant="secondary" size="sm" :disabled="count($selectedPropertyIds) === 0">
                                Unpublish
                            </x-admin.button>
                        </div>

                        <x-admin.button wire:click="clearSelection" variant="quiet" size="sm" :disabled="count($selectedPropertyIds) === 0">
                            Clear Selection
                        </x-admin.button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell w-14">
                                        <label class="inline-flex items-center justify-center">
                                            <span class="sr-only">Select all properties on this page</span>
                                            <input
                                                type="checkbox"
                                                class="admin-checkbox"
                                                wire:model.live="selectPage"
                                            />
                                        </label>
                                    </th>
                                    <th class="admin-table-head-cell">Property</th>
                                    <th class="admin-table-head-cell">Landlord</th>
                                    <th class="admin-table-head-cell">Location</th>
                                    <th class="admin-table-head-cell">Status</th>
                                    <th class="admin-table-head-cell">Publish State</th>
                                    <th class="admin-table-head-cell">Inventory</th>
                                    <th class="admin-table-head-cell">Uploads</th>
                                    <th class="admin-table-head-cell"></th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($properties as $property)
                                    <tr>
                                        <td class="px-4 py-4 align-top">
                                            <label class="inline-flex items-center justify-center">
                                                <span class="sr-only">Select {{ $property->title }}</span>
                                                <input
                                                    type="checkbox"
                                                    class="admin-checkbox"
                                                    wire:model.live="selectedPropertyIds"
                                                    value="{{ $property->id }}"
                                                />
                                            </label>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="font-medium text-slate-900">{{ $property->title }}</div>
                                            <div class="text-sm text-slate-500">{{ str($property->property_type)->headline() }}</div>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">{{ $property->landlord?->name }}</td>
                                        <td class="px-4 py-4 text-sm text-slate-600">{{ $property->area }}, {{ $property->city }}</td>
                                        <td class="px-4 py-4"><x-admin.badge>{{ str($property->status)->headline() }}</x-admin.badge></td>
                                        <td class="px-4 py-4">
                                            @if ($property->status === 'approved' && $property->is_verified && $property->is_published)
                                                <x-admin.badge tone="success">Published</x-admin.badge>
                                            @elseif ($property->status === 'approved' && $property->is_verified)
                                                <x-admin.badge tone="warning">Approved, Unpublished</x-admin.badge>
                                            @else
                                                <x-admin.badge>Not eligible</x-admin.badge>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">
                                            <p class="font-medium text-slate-900">{{ $property->availabilityLabel() }}</p>
                                            <p class="mt-1">{{ $property->available_units }} available / {{ $property->total_units }} total</p>
                                            <p class="mt-1">{{ $property->occupied_units }} occupied</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600">{{ $property->images_count }} images, {{ $property->documents_count }} documents</td>
                                        <td class="px-4 py-4 text-right"><x-admin.action-link href="{{ route('admin.properties.show', $property) }}">Open Review</x-admin.action-link></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8">
                                            <x-admin.empty-state
                                                title="No properties match the current search or filters."
                                                copy="Try a broader property search or adjust the review and publish-state filters."
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $properties->links() }}
            </div>
        </x-admin.panel>
    </div>
</div>
