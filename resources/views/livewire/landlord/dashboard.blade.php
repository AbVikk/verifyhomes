<div class="admin-page">
    <div class="admin-page-inner">
        @if (session('status'))
            <x-admin.panel>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            </x-admin.panel>
        @endif

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Profile completion</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $completionPercentage }}%</p>
                    <p class="text-sm text-slate-600">
                        Verification status:
                        <span class="font-medium text-slate-900">{{ str($profile?->verification_status ?? 'pending')->headline() }}</span>
                    </p>
                    <a href="{{ route('landlord.profile') }}" class="admin-inline-link">Complete profile</a>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Verification documents</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $documentsAvailable ? $documentCount : 'Unavailable' }}</p>
                    @if ($documentsAvailable)
                        <p class="text-sm text-slate-600">
                            Latest status:
                            <span class="font-medium text-slate-900">
                                {{ $latestDocument ? str($latestDocument->review_status)->headline() : 'No documents uploaded yet' }}
                            </span>
                        </p>
                        <a href="{{ route('landlord.documents') }}" class="admin-inline-link">Manage documents</a>
                    @else
                        <p class="text-sm text-slate-600">Verification document data is not available yet in this environment.</p>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Properties submitted</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $propertyCount }}</p>
                    <p class="text-sm text-slate-600">Total listings currently in your workspace.</p>
                    <a href="{{ route('landlord.properties') }}" class="admin-inline-link">Manage properties</a>
                </div>
            </x-admin.panel>

            <x-admin.panel class="h-full">
                <div class="space-y-2">
                    <p class="admin-eyebrow">Open inspection requests</p>
                    <p class="text-3xl font-semibold text-slate-950">{{ $inspectionRequestsAvailable ? $openInspectionRequestCount : 'Unavailable' }}</p>
                    <p class="text-sm text-slate-600">Requested and scheduled visits that still need landlord follow-through.</p>
                    <a href="{{ route('landlord.inspection-requests.index') }}" class="admin-inline-link">Open inspection requests</a>
                </div>
            </x-admin.panel>
        </div>

        <div class="grid gap-6">
            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Getting started</p>
                        <h3 class="admin-panel-title">Landlord onboarding checklist</h3>
                        <p class="admin-panel-copy">Clear these basics so listings and inspections flow smoothly.</p>
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
                        <p class="admin-panel-copy">Short, clear next steps based on your latest landlord activity.</p>
                    </div>

                    @if ($nextActions === [])
                        <x-admin.empty-state
                            title="Everything looks steady right now."
                            copy="We will surface the next landlord step here as requests and listings change."
                        />
                    @else
                        <div class="space-y-3">
                            @foreach ($nextActions as $action)
                                <a href="{{ $action['href'] }}" class="block rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-slate-300 hover:bg-slate-50">
                                    <p class="text-sm font-semibold text-slate-900">{{ $action['label'] }}</p>
                                    <p class="mt-2 text-sm text-slate-600">{{ $action['context'] }}</p>
                                    <p class="mt-4 text-sm font-medium text-sky-700">{{ $action['cta'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.panel>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Needs attention now</p>
                        <h3 class="admin-panel-title">Current landlord priorities</h3>
                        <p class="admin-panel-copy">A short list of onboarding, listing, and coordination items that still need landlord awareness.</p>
                    </div>

                    @if ($attentionItems === [])
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">Nothing urgent is stacked up right now.</h4>
                            <p class="admin-empty-state-copy">New landlord tasks will show here as your listings and requests change.</p>
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

        <div class="grid gap-6 lg:grid-cols-2">
            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Listing status summary</p>
                        <h3 class="admin-panel-title">Current listing pipeline</h3>
                        <p class="admin-panel-copy">Use this snapshot to see what is in review, ready for follow-through, live, or already driving tenant activity.</p>
                    </div>

                    <div class="space-y-3">
                        <a href="{{ route('landlord.properties') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <span class="text-sm font-medium text-slate-700">Pending review</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $pendingReviewPropertiesCount }}</span>
                        </a>
                        <a href="{{ route('landlord.properties') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <span class="text-sm font-medium text-slate-700">Approved, unpublished</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $approvedUnpublishedPropertiesCount }}</span>
                        </a>
                        <a href="{{ route('landlord.properties') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <span class="text-sm font-medium text-slate-700">Live listings</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $livePublishedPropertiesCount }}</span>
                        </a>
                        <a href="{{ route('landlord.inspection-requests.index') }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 transition hover:border-slate-300 hover:bg-slate-50">
                            <span class="text-sm font-medium text-slate-700">Scheduled visits</span>
                            <span class="text-sm font-semibold text-slate-900">{{ $inspectionRequestsAvailable ? $scheduledInspectionRequestCount : 'Unavailable' }}</span>
                        </a>
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Operational shortcuts</p>
                        <h3 class="admin-panel-title">Direct actions</h3>
                        <p class="admin-panel-copy">Move directly into the next landlord page that usually matters most day to day.</p>
                    </div>

                    @unless ($canCreateProperties)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {{ $propertyCreationBlockMessage }}
                        </div>
                    @endunless

                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('landlord.profile') }}" class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Complete Profile
                        </a>
                        @if ($documentsAvailable)
                            <a href="{{ route('landlord.documents') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900">
                                Upload Verification Documents
                            </a>
                        @endif
                        <a href="{{ route('landlord.properties') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900">
                            Manage Properties
                        </a>
                        <a href="{{ route('landlord.inspection-requests.index') }}" class="inline-flex items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400 hover:text-slate-900">
                            Manage Inspection Requests
                        </a>
                        @if ($canCreateProperties)
                            <a href="{{ route('landlord.properties.create') }}" class="inline-flex items-center rounded-md border border-emerald-300 px-4 py-2 text-sm font-medium text-emerald-700 hover:border-emerald-400 hover:text-emerald-800">
                                Create Property
                            </a>
                        @endif
                    </div>
                </div>
            </x-admin.panel>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Recent listing updates</p>
                        <h3 class="admin-panel-title">Latest property changes</h3>
                        <p class="admin-panel-copy">Your latest property records and the clearest next landlord step for each one.</p>
                    </div>

                    <div class="space-y-3">
                        @forelse ($recentProperties as $property)
                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $property->title }}</p>
                                        <p class="mt-1 text-sm text-slate-600">{{ $property->area }}, {{ $property->city }}</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                        {{ str($property->status)->headline() }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm text-slate-600">{{ $this->propertyActionSummary($property) }}</p>
                                <p class="mt-2 text-sm text-slate-500">Updated {{ $property->updated_at?->diffForHumans() ?? 'recently' }}</p>

                                <div class="mt-3 flex flex-wrap gap-3">
                                    <a href="{{ route('landlord.properties.edit', $property) }}" class="admin-inline-link inline-flex">Open listing</a>
                                    @if ($property->open_inspection_requests_count > 0)
                                        <a href="{{ route('landlord.inspection-requests.index') }}" class="admin-inline-link inline-flex">Open request queue</a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="admin-empty-state">
                                <h4 class="admin-empty-state-title">No properties have been submitted yet.</h4>
                                <p class="admin-empty-state-copy">Add your first listing to start the review process.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </x-admin.panel>

            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Inspection coordination</p>
                        <h3 class="admin-panel-title">Recent tenant activity</h3>
                        <p class="admin-panel-copy">Recent tenant inspection activity tied to your listings, with a clearer follow-through cue for each request.</p>
                    </div>

                    @if (! $inspectionRequestsAvailable)
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">Inspection request data is not available yet.</h4>
                            <p class="admin-empty-state-copy">This section will populate automatically after the inspection workflow tables are migrated in this environment.</p>
                        </div>
                    @else
                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <p class="text-sm font-medium text-slate-500">New requested visits</p>
                                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $requestedInspectionRequestCount }}</p>
                                <p class="mt-2 text-sm text-slate-600">Tenants still waiting for the next scheduling step.</p>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                <p class="text-sm font-medium text-slate-500">Scheduled visits</p>
                                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $scheduledInspectionRequestCount }}</p>
                                <p class="mt-2 text-sm text-slate-600">Visits already on the calendar that still need landlord readiness.</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            @forelse ($recentInspectionRequests as $inspectionRequest)
                                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-slate-900">{{ $inspectionRequest->property?->title ?? 'Inspection request' }}</p>
                                            <p class="mt-1 text-sm text-slate-600">{{ str($inspectionRequest->status)->headline() }}</p>
                                            @if ($inspectionRequest->scheduled_at)
                                                <p class="mt-1 text-sm text-slate-600">Scheduled: {{ $inspectionRequest->scheduled_at->format('M j, Y g:i A') }}</p>
                                            @elseif ($inspectionRequest->preferred_date)
                                                <p class="mt-1 text-sm text-slate-600">Preferred date: {{ $inspectionRequest->preferred_date->toFormattedDateString() }}</p>
                                            @endif
                                            <p class="mt-2 text-sm text-slate-600">{{ $this->inspectionRequestActionSummary($inspectionRequest) }}</p>
                                        </div>
                                        <a href="{{ route('landlord.inspection-requests.show', ['inspectionRequestId' => $inspectionRequest->getKey()]) }}" class="admin-inline-link">View request</a>
                                    </div>
                                </div>
                            @empty
                                <div class="admin-empty-state">
                                    <h4 class="admin-empty-state-title">No inspection requests are tied to your listings yet.</h4>
                                    <p class="admin-empty-state-copy">New tenant coordination items will show up here automatically.</p>
                                </div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </x-admin.panel>
        </div>
    </div>
</div>
