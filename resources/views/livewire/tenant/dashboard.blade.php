<div class="admin-page">
    <div class="admin-page-inner">
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-5">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Inspection requests</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $inspectionRequestCount }}</p>
                    <a href="{{ route('tenant.inspection-requests.index') }}" class="admin-inline-link">View requests</a>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Saved listings</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $savedListingsAvailable ? $savedListingsCount : 0 }}</p>
                    <p class="text-sm text-slate-600">Properties you shortlisted to revisit or compare later.</p>
                    <a href="{{ route('tenant.saved-listings.index') }}" class="admin-inline-link">Open saved listings</a>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Open requests</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $inspectionRequestsAvailable ? $openInspectionRequestCount : 0 }}</p>
                    <p class="text-sm text-slate-600">Requested or scheduled visits that still need follow-through.</p>
                    <a href="{{ route('tenant.inspection-requests.index') }}" class="admin-inline-link">Manage open requests</a>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Closed requests</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $inspectionRequestsAvailable ? $closedInspectionRequestCount : 0 }}</p>
                    <p class="text-sm text-slate-600">Completed, rejected, or cancelled requests.</p>
                    <a href="{{ route('tenant.inspection-requests.index') }}" class="admin-inline-link">Review request history</a>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Next scheduled visit</p>
                    <p class="text-lg font-semibold text-slate-950">
                        {{ $upcomingInspectionRequest?->scheduled_at?->format('M j, g:i A') ?? 'No visit scheduled' }}
                    </p>
                    <p class="text-sm text-slate-600">
                        {{ $upcomingInspectionRequest?->property?->title ?? 'We will show your next confirmed inspection here.' }}
                    </p>
                    <a href="{{ $upcomingInspectionRequest ? route('tenant.inspection-requests.show', ['inspectionRequestId' => $upcomingInspectionRequest->getKey()]) : route('tenant.inspection-requests.index') }}" class="admin-inline-link">
                        {{ $upcomingInspectionRequest ? 'View scheduled visit' : 'View requests' }}
                    </a>
                </div>
            </x-admin.panel>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-admin.panel class="lg:col-span-2">
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Getting started</p>
                        <h3 class="admin-panel-title">Tenant onboarding checklist</h3>
                        <p class="admin-panel-copy">Complete the basics so rent and purchase flows unlock cleanly.</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($checklist as $item)
                            <a href="{{ $item['href'] }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ $item['label'] }}</p>
                                    <p class="text-xs text-slate-500">{{ $item['complete'] ? 'Done' : 'Needs attention' }}</p>
                                </div>
                                <x-status-chip tone="{{ $item['complete'] ? 'success' : 'warning' }}">
                                    {{ $item['complete'] ? 'Complete' : 'Pending' }}
                                </x-status-chip>
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Next actions</p>
                        <h3 class="admin-panel-title">What needs your attention</h3>
                        <p class="admin-panel-copy">Short, clear next steps based on your latest activity.</p>
                    </div>

                    @if ($nextActions === [])
                        <x-admin.empty-state
                            title="No urgent items right now."
                            copy="We will surface the next step here as inspection and payment updates arrive."
                        />
                    @else
                        <div class="space-y-3">
                            @foreach ($nextActions as $action)
                                <a href="{{ $action['href'] }}" class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                    <p class="text-sm font-semibold text-slate-900">{{ $action['label'] }}</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ $action['context'] }}</p>
                                    <p class="mt-3 text-sm font-medium text-sky-700">{{ $action['cta'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.panel>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <x-admin.panel>
                    <div class="space-y-4">
                        <div>
                            <p class="admin-eyebrow">Tenant home base</p>
                            <h3 class="admin-panel-title">Current tenant workspace</h3>
                            <p class="admin-panel-copy">Browse approved properties, keep a shortlist of saved listings, update your tenant profile, and track inspection payments and requests from one tenant workspace.</p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a href="{{ route('properties.index') }}" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Browse Properties</a>
                            <a href="{{ route('tenant.profile') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900">Manage Profile</a>
                            <a href="{{ route('tenant.saved-listings.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900">View Saved Listings</a>
                            <a href="{{ route('tenant.payments.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900">View Payments</a>
                            <a href="{{ route('tenant.inspection-requests.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900">View Inspection Requests</a>
                        </div>
                    </div>
                </x-admin.panel>
            </div>

            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">What needs attention</p>
                        <h3 class="admin-panel-title">Current request priorities</h3>
                        <p class="admin-panel-copy">Current request changes that matter most right now.</p>
                    </div>

                    @if (! $inspectionRequestsAvailable)
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">Inspection request data is not available yet.</h4>
                            <p class="admin-empty-state-copy">This section will populate automatically after the inspection workflow tables are migrated in this environment.</p>
                        </div>
                    @elseif ($attentionItems === [])
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">Nothing urgent is stacked up right now.</h4>
                            <p class="admin-empty-state-copy">New scheduling changes and completed outcomes will show here automatically.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($attentionItems as $attentionItem)
                                <a href="{{ $attentionItem['href'] }}" class="block rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-slate-300 hover:bg-slate-50">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $attentionItem['label'] }}</p>
                                            <p class="mt-2 text-sm text-slate-600">{{ $attentionItem['context'] }}</p>
                                        </div>
                                        <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold text-white">{{ $attentionItem['value'] }}</span>
                                    </div>

                                    <p class="mt-4 text-sm font-medium text-sky-700">{{ $attentionItem['cta'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                <div>
                    <p class="admin-eyebrow">Recent inspection requests</p>
                    <h3 class="admin-panel-title">Latest request updates</h3>
                    <p class="admin-panel-copy">
                        Your most recent requests and their current statuses.
                        @if ($latestInspectionRequest)
                            Latest change: {{ str($latestInspectionRequest->status)->headline() }} for {{ $latestInspectionRequest->property?->title }}.
                        @endif
                    </p>
                </div>

                @if (! $inspectionRequestsAvailable)
                    <div class="admin-empty-state">
                        <h4 class="admin-empty-state-title">Inspection request data is not available yet.</h4>
                        <p class="admin-empty-state-copy">This section will populate automatically after the inspection workflow tables are migrated in this environment.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @forelse ($inspectionRequests as $inspectionRequest)
                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $inspectionRequest->property?->title }}</p>
                                        <p class="text-sm text-slate-600">{{ $inspectionRequest->property?->area }}, {{ $inspectionRequest->property?->city }}</p>
                                        @if ($inspectionRequest->preferred_date)
                                            <p class="mt-1 text-sm text-slate-600">Preferred date: {{ $inspectionRequest->preferred_date->toFormattedDateString() }}</p>
                                        @endif
                                        @if ($inspectionRequest->scheduled_at)
                                            <p class="mt-1 text-sm text-slate-600">Scheduled: {{ $inspectionRequest->scheduled_at->format('M j, Y g:i A') }}</p>
                                        @endif
                                        @if ($inspectionRequest->outcome_type)
                                            <p class="mt-1 text-sm text-slate-600">Update: {{ $outcomes[$inspectionRequest->outcome_type] }}</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-col items-start gap-2 md:items-end">
                                        <x-status-chip tone="{{ $inspectionRequest->status === 'completed' ? 'success' : ($inspectionRequest->status === 'scheduled' ? 'info' : 'warning') }}">
                                            {{ str($inspectionRequest->status)->headline() }}
                                        </x-status-chip>
                                        <a href="{{ route('tenant.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]) }}" class="admin-inline-link">View details</a>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="admin-empty-state">
                                <h4 class="admin-empty-state-title">You have not submitted any inspection requests yet.</h4>
                                <p class="admin-empty-state-copy">Request activity will start appearing here once you book your first inspection.</p>
                                <a href="{{ route('properties.index') }}" class="admin-inline-link mt-3 inline-flex">Browse listings</a>
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        </x-admin.panel>
    </div>
</div>
