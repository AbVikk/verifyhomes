<div class="admin-page">
    <div class="admin-page-inner">
        <x-admin.panel>
            <div class="space-y-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-950">Audit log</h3>
                    <p class="mt-1 text-sm text-slate-600">Review the live admin and staff workflow trail here. Search by actor, action, subject, or target type to follow what changed and who changed it.</p>
                </div>

                @if (! $auditAvailable)
                    <x-admin.empty-state
                        title="Audit logging is not available yet."
                        copy="This page will populate automatically after the audit logging table is available in this environment."
                    />
                @else
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        Showing {{ $auditLogs->count() }} entries on this page from {{ number_format($totalAuditEntries) }} total logged records.
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.7fr)_minmax(0,0.7fr)_minmax(0,0.55fr)_minmax(0,0.65fr)_minmax(0,0.55fr)_minmax(0,0.55fr)]">
                        <div>
                            <x-admin.label for="search">Search audit log</x-admin.label>
                            <x-admin.input
                                wire:model.live.debounce.300ms="search"
                                id="search"
                                type="search"
                                placeholder="Search action, actor, target, or description"
                            />
                        </div>

                        @if ($actionOptions !== [])
                            <div>
                                <x-admin.label for="actionFilter">Filter by action</x-admin.label>
                                <x-admin.select wire:model.live="actionFilter" id="actionFilter">
                                    <option value="all">All actions</option>
                                    @foreach ($actionOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-admin.select>
                            </div>
                        @endif

                        @if ($targetTypeOptions !== [])
                            <div>
                                <x-admin.label for="targetTypeFilter">Filter by subject type</x-admin.label>
                                <x-admin.select wire:model.live="targetTypeFilter" id="targetTypeFilter">
                                    <option value="all">All subject types</option>
                                    @foreach ($targetTypeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-admin.select>
                            </div>
                        @endif

                        <div>
                            <x-admin.label for="perPage">Per page</x-admin.label>
                            <x-admin.select wire:model.live="perPage" id="perPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </x-admin.select>
                        </div>

                        @if ($dateFilterAvailable)
                            <div>
                                <x-admin.label for="sortDirection">Sort by logged time</x-admin.label>
                                <x-admin.select wire:model.live="sortDirection" id="sortDirection">
                                    <option value="desc">Newest first</option>
                                    <option value="asc">Oldest first</option>
                                </x-admin.select>
                            </div>
                        @endif

                        @if ($dateFilterAvailable)
                            <div>
                                <x-admin.label for="fromDate">From date</x-admin.label>
                                <x-admin.input
                                    wire:model.live="fromDate"
                                    id="fromDate"
                                    type="date"
                                />
                            </div>

                            <div>
                                <x-admin.label for="toDate">To date</x-admin.label>
                                <x-admin.input
                                    wire:model.live="toDate"
                                    id="toDate"
                                    type="date"
                                />
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end">
                        <x-admin.button wire:click="resetFilters" variant="quiet" size="sm">
                            Reset Filters
                        </x-admin.button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="admin-table-head">
                                <tr>
                                    <th class="admin-table-head-cell">Action</th>
                                    <th class="admin-table-head-cell">Actor</th>
                                    <th class="admin-table-head-cell">Subject</th>
                                    <th class="admin-table-head-cell">Context</th>
                                    <th class="admin-table-head-cell">Logged</th>
                                </tr>
                            </thead>
                            <tbody class="admin-table-body">
                                @forelse ($auditLogs as $auditLog)
                                    @php
                                        $action = collect([
                                            $auditLog->action ?? null,
                                            $auditLog->event ?? null,
                                            $auditLog->label ?? null,
                                        ])->first(fn ($value) => filled($value)) ?? 'Audit entry';

                                        $actorName = collect([
                                            $auditLog->actor_name ?? null,
                                            $auditLog->user_name ?? null,
                                        ])->first(fn ($value) => filled($value)) ?? 'Unknown user';

                                        $actorEmail = collect([
                                            $auditLog->actor_email ?? null,
                                            $auditLog->user_email ?? null,
                                        ])->first(fn ($value) => filled($value));

                                        $target = collect([
                                            $auditLog->target_label ?? null,
                                            $auditLog->entity_label ?? null,
                                            $auditLog->target ?? null,
                                            $auditLog->entity ?? null,
                                        ])->first(fn ($value) => filled($value)) ?? 'Not available';

                                        $targetType = $auditLog->target_type ?? null;
                                        $targetTypeLabel = filled($targetType)
                                            ? ($targetType === 'string' ? 'General' : str(class_basename($targetType))->headline()->toString())
                                            : 'General';

                                        $metadata = [];

                                        if (isset($auditLog->metadata)) {
                                            if (is_array($auditLog->metadata)) {
                                                $metadata = $auditLog->metadata;
                                            } elseif (is_object($auditLog->metadata)) {
                                                $metadata = (array) $auditLog->metadata;
                                            } elseif (is_string($auditLog->metadata) && $auditLog->metadata !== '') {
                                                $decodedMetadata = json_decode($auditLog->metadata, true);
                                                $metadata = is_array($decodedMetadata) ? $decodedMetadata : [];
                                            }
                                        }

                                        $metadataLines = collect();

                                        if (filled($metadata['from_status'] ?? null) || filled($metadata['to_status'] ?? null)) {
                                            $metadataLines->push('Status: '.str($metadata['from_status'] ?? 'Unknown')->headline().' -> '.str($metadata['to_status'] ?? 'Unknown')->headline());
                                        }

                                        if (filled($metadata['status'] ?? null) && ! filled($metadata['from_status'] ?? null)) {
                                            $metadataLines->push('Status context: '.str($metadata['status'])->headline());
                                        }

                                        if (filled($metadata['source_type'] ?? null)) {
                                            $metadataLines->push('Source: '.str($metadata['source_type'])->headline());
                                        }

                                        if (filled($metadata['scheduled_at'] ?? null)) {
                                            $metadataLines->push('Scheduled at: '.\Illuminate\Support\Carbon::parse($metadata['scheduled_at'])->format('Y-m-d H:i'));
                                        }

                                        if (filled($metadata['outcome_type'] ?? null)) {
                                            $metadataLines->push('Outcome: '.str($metadata['outcome_type'])->headline());
                                        }

                                        if (filled($metadata['notes'] ?? null)) {
                                            $metadataLines->push('Notes: '.str($metadata['notes'])->limit(80));
                                        }

                                        if ($metadataLines->isEmpty() && $metadata !== []) {
                                            foreach ($metadata as $key => $value) {
                                                if (is_scalar($value) && filled((string) $value)) {
                                                    $metadataLines->push(str($key)->headline().': '.$value);
                                                }

                                                if ($metadataLines->count() === 3) {
                                                    break;
                                                }
                                            }
                                        }

                                        $loggedAt = $auditLog->created_at ?? null;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-4 text-sm text-slate-700 align-top">
                                            <p class="font-medium text-slate-900">{{ str($action)->headline() }}</p>
                                            @if (! empty($auditLog->description))
                                                @if (str($auditLog->description)->length() > 140)
                                                    <div class="mt-1">
                                                        <p class="text-slate-500">{{ str($auditLog->description)->limit(140) }}</p>
                                                        <details class="mt-2 text-slate-500">
                                                            <summary class="cursor-pointer text-sm font-medium text-slate-700">Show more</summary>
                                                            <p class="mt-2">{{ $auditLog->description }}</p>
                                                        </details>
                                                    </div>
                                                @else
                                                    <p class="mt-1 text-slate-500">{{ $auditLog->description }}</p>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600 align-top">
                                            <p class="font-medium text-slate-900">{{ $actorName }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $actorEmail ?: 'Not available' }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600 align-top">
                                            <p class="font-medium text-slate-900">{{ $target }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $targetTypeLabel }}</p>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600 align-top">
                                            @if ($metadataLines->isEmpty())
                                                <p>No extra context recorded.</p>
                                            @else
                                                <div class="space-y-1">
                                                    @foreach ($metadataLines as $line)
                                                        <p>{{ $line }}</p>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-slate-600 align-top">
                                            @if ($loggedAt)
                                                @php
                                                    $parsedLoggedAt = \Illuminate\Support\Carbon::parse($loggedAt);
                                                @endphp
                                                <p>{{ $parsedLoggedAt->diffForHumans() }}</p>
                                                <p class="mt-1 text-xs text-slate-500" title="{{ $parsedLoggedAt->toDateTimeString() }}">
                                                    {{ $parsedLoggedAt->format('Y-m-d H:i:s') }}
                                                </p>
                                            @else
                                                Not available
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8">
                                            @if ($totalAuditEntries === 0)
                                                <x-admin.empty-state
                                                    title="No audit entries have been logged yet."
                                                    copy="This list will populate automatically once audit activity starts being recorded in this environment."
                                                />
                                            @else
                                                <x-admin.empty-state
                                                    title="No audit entries match the current search or filter."
                                                    copy="Try a broader search, adjust the action filter, or widen the selected date range."
                                                />
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $auditLogs->links() }}
                @endif
            </div>
        </x-admin.panel>
    </div>
</div>
