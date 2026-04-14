@php
    $inspectionPoints = collect($inspectionTrend['points']);
    $inspectionMax = max($inspectionPoints->max() ?? 0, 1);
    $inspectionStepX = $inspectionPoints->count() > 1 ? 100 / ($inspectionPoints->count() - 1) : 100;
    $inspectionLinePoints = $inspectionPoints
        ->map(function ($point, $index) use ($inspectionMax, $inspectionStepX) {
            $x = $index * $inspectionStepX;
            $y = 88 - (($point / $inspectionMax) * 72);

            return number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
        })
        ->implode(' ');

    $inspectionAreaPoints = trim('0,88 '.$inspectionLinePoints.' 100,88');

    $donutCircumference = 2 * pi() * 44;
    $donutOffset = 0;
    $landlordSparkline = collect($landlordPipeline['rows'])->pluck('value')->values()->all();
    $propertySparkline = collect($publishReadiness['segments'])->pluck('value')->values()->all();
    $openInspectionSparkline = [$requestedInspectionCount, $inspectionTrend['requested'], $inspectionTrend['scheduled'], $inspectionTrend['completed']];
    $closedInspectionSparkline = [$closedInspectionCount, $inspectionTrend['completed'], $inspectionTrend['scheduled'], $inspectionTrend['requested']];
@endphp

<div class="admin-page">
    <div class="admin-page-inner">
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <x-admin.stat-card label="Pending landlords" :value="$pendingLandlordsCount" icon="landlords" note="Awaiting verification decision" :trend="$landlordSparkline ?: [0]" />
            <x-admin.stat-card label="Pending properties" :value="$pendingPropertiesCount" icon="properties" note="Waiting in the review queue" :trend="$propertySparkline ?: [0]" />
            <x-admin.stat-card label="Approved, unpublished" :value="$approvedUnpublishedPropertiesCount" icon="properties" note="Ready for a publish decision" :trend="[$pendingPropertiesCount, $approvedUnpublishedPropertiesCount, $livePublishedPropertiesCount, $rejectedOrSuspendedPropertiesCount]" />
            <x-admin.stat-card label="Live published properties" :value="$livePublishedPropertiesCount" icon="properties" note="Visible in public discovery" :trend="[$livePublishedPropertiesCount, $approvedPropertiesCount, $approvedUnpublishedPropertiesCount, $pendingPropertiesCount]" />
            <x-admin.stat-card label="Open inspection requests" :value="$requestedInspectionCount" icon="inspection" note="Active coordination workload" :trend="$openInspectionSparkline" />
            <x-admin.stat-card label="Closed inspection requests" :value="$closedInspectionCount" icon="inspection" note="Completed, rejected, or cancelled" :trend="$closedInspectionSparkline" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-admin.panel>
                <div class="space-y-4">
                    <div>
                        <p class="admin-eyebrow">Getting started</p>
                        <h3 class="admin-panel-title">Admin onboarding checklist</h3>
                        <p class="admin-panel-copy">Keep reviews and follow-through tight with these core admin steps.</p>
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
                        <h3 class="admin-panel-title">What needs attention now</h3>
                        <p class="admin-panel-copy">Immediate operational steps based on current review and coordination queues.</p>
                    </div>

                    @if ($nextActions === [])
                        <x-admin.empty-state
                            title="No urgent admin actions right now."
                            copy="We will highlight the next action as queues change."
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

        <x-admin.panel>
            <div class="space-y-5">
                <div>
                    <p class="admin-eyebrow">Platform fee summary</p>
                    <h3 class="admin-panel-title">Admin earnings from payment transactions</h3>
                    <p class="admin-panel-copy">Platform earnings come from paid transaction records, not from review queues. Each paid transaction stores the gross amount, the applied platform fee percentage, the platform fee amount, and the net amount after the fee.</p>
                </div>

                @if (! $paymentSummary['hasDataSource'])
                    <div class="admin-empty-state">
                        <h4 class="admin-empty-state-title">Payment transaction data is not available yet.</h4>
                        <p class="admin-empty-state-copy">Create the payment transaction table before wiring checkout or settlement flows to platform-fee reporting.</p>
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Paid transactions</span>
                            <span class="admin-micro-stat-value">{{ $paymentSummary['paidTransactionsCount'] }}</span>
                        </div>
                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Gross paid volume</span>
                            <span class="admin-micro-stat-value">{{ $this->formatMoney($paymentSummary['grossAmount']) }}</span>
                        </div>
                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Platform fee earned</span>
                            <span class="admin-micro-stat-value">{{ $this->formatMoney($paymentSummary['platformFeeAmount']) }}</span>
                        </div>
                        <div class="admin-micro-stat">
                            <span class="admin-micro-stat-label">Net after platform fee</span>
                            <span class="admin-micro-stat-value">{{ $this->formatMoney($paymentSummary['netAmount']) }}</span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        Default configured platform fee: <span class="font-medium text-slate-900">{{ number_format($paymentSummary['configuredPercentage'], 2) }}%</span>.
                        New transaction records should calculate and store fee amounts at payment time so reporting always reads from immutable transaction data.
                    </div>
                @endif
            </div>
        </x-admin.panel>

        <div class="grid gap-6 md:grid-cols-12">
            <x-admin.panel class="md:col-span-12">
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Needs attention now</p>
                        <h3 class="admin-panel-title">Current operational priorities</h3>
                        <p class="admin-panel-copy">A short list of live queue items that still need an admin decision or follow-through.</p>
                    </div>

                    @if ($attentionItems === [])
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">No urgent admin follow-up is stacked up right now.</h4>
                            <p class="admin-empty-state-copy">New review items and coordination work will appear here automatically as activity comes in.</p>
                        </div>
                    @else
                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach ($attentionItems as $attentionItem)
                                <a href="{{ $attentionItem['href'] }}" class="block rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-slate-300 hover:bg-slate-50">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $attentionItem['label'] }}</p>
                                            <p class="mt-2 text-sm text-slate-600">{{ $attentionItem['context'] }}</p>
                                        </div>
                                        <span class="rounded-full bg-slate-900 px-3 py-1 text-sm font-semibold text-white">{{ $attentionItem['value'] }}</span>
                                    </div>

                                    <p class="mt-4 text-sm font-medium text-sky-700">{{ $attentionItem['cta'] }}</p>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel class="md:col-span-8">
                <div class="space-y-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="admin-eyebrow">Inspection activity overview</p>
                            <h3 class="admin-panel-title">Request volume over the last 14 days</h3>
                            <p class="admin-panel-copy">Track inspection intake while keeping an eye on how much of the queue is still active versus already resolved.</p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3 sm:min-w-[320px]">
                            <div class="admin-micro-stat">
                                <span class="admin-micro-stat-label">Requested</span>
                                <span class="admin-micro-stat-value">{{ $inspectionTrend['requested'] }}</span>
                            </div>
                            <div class="admin-micro-stat">
                                <span class="admin-micro-stat-label">Scheduled</span>
                                <span class="admin-micro-stat-value">{{ $inspectionTrend['scheduled'] }}</span>
                            </div>
                            <div class="admin-micro-stat">
                                <span class="admin-micro-stat-label">Completed</span>
                                <span class="admin-micro-stat-value">{{ $inspectionTrend['completed'] }}</span>
                            </div>
                        </div>
                    </div>

                    @if (! $hasInspectionRequestData)
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">Inspection request data is not available yet.</h4>
                            <p class="admin-empty-state-copy">This panel will populate automatically after the inspection workflow tables are migrated in this environment.</p>
                        </div>
                    @elseif ($inspectionTrend['total'] === 0)
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">No inspection requests have been created in the last 14 days.</h4>
                            <p class="admin-empty-state-copy">Recent volume will appear here as soon as tenants start booking inspections again.</p>
                        </div>
                    @else
                        <div class="admin-chart-shell">
                            <div class="admin-chart-summary">
                                <span class="admin-chart-total">{{ $inspectionTrend['total'] }}</span>
                                <span class="admin-chart-context">requests logged in the current two-week window</span>
                            </div>

                            <svg class="admin-line-chart" viewBox="0 0 100 96" preserveAspectRatio="none" aria-hidden="true">
                                <path class="admin-line-chart-grid" d="M0 88H100" />
                                <path class="admin-line-chart-grid" d="M0 60H100" />
                                <path class="admin-line-chart-grid" d="M0 32H100" />
                                <polygon class="admin-line-chart-area" points="{{ $inspectionAreaPoints }}" />
                                <polyline class="admin-line-chart-line" points="{{ $inspectionLinePoints }}" />
                            </svg>

                            <div class="admin-chart-axis">
                                @foreach ($inspectionTrend['labels'] as $index => $label)
                                    @if ($index === 0 || $index === count($inspectionTrend['labels']) - 1 || $index % 3 === 0)
                                        <span>{{ $label }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel class="md:col-span-4">
                <div class="space-y-6">
                    <div>
                        <p class="admin-eyebrow">Publish readiness overview</p>
                        <h3 class="admin-panel-title">Review and publication breakdown</h3>
                        <p class="admin-panel-copy">See how much inventory is still waiting for review, ready for publishing, already live, or blocked from going out.</p>
                    </div>

                    @if ($publishReadiness['total'] === 0)
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">No property records yet.</h4>
                            <p class="admin-empty-state-copy">As listings are submitted, this breakdown will show how much inventory is ready to review and publish.</p>
                        </div>
                    @else
                        <div class="admin-donut-layout">
                            <div class="admin-donut-shell">
                                <svg class="admin-donut-chart" viewBox="0 0 120 120" aria-hidden="true">
                                    <circle class="admin-donut-track" cx="60" cy="60" r="44" />
                                    @foreach ($publishReadiness['segments'] as $segment)
                                        @php
                                            $segmentLength = $publishReadiness['total'] > 0
                                                ? ($segment['value'] / $publishReadiness['total']) * $donutCircumference
                                                : 0;
                                        @endphp
                                        <circle
                                            class="admin-donut-segment admin-tone-{{ $segment['tone'] }}"
                                            cx="60"
                                            cy="60"
                                            r="44"
                                            stroke-dasharray="{{ number_format($segmentLength, 2, '.', '') }} {{ number_format($donutCircumference - $segmentLength, 2, '.', '') }}"
                                            stroke-dashoffset="-{{ number_format($donutOffset, 2, '.', '') }}"
                                        />
                                        @php($donutOffset += $segmentLength)
                                    @endforeach
                                </svg>

                                <div class="admin-donut-center">
                                    <span class="admin-donut-total">{{ $publishReadiness['total'] }}</span>
                                    <span class="admin-donut-label">total listings</span>
                                </div>
                            </div>

                            <div class="space-y-3">
                                @foreach ($publishReadiness['segments'] as $segment)
                                    <div class="admin-legend-row">
                                        <div class="flex items-center gap-3">
                                            <span class="admin-legend-dot admin-tone-{{ $segment['tone'] }}"></span>
                                            <span class="admin-legend-label">{{ $segment['label'] }}</span>
                                        </div>
                                        <span class="admin-legend-value">{{ $segment['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel class="md:col-span-8">
                <div class="space-y-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="admin-eyebrow">Recent review activity</p>
                            <h3 class="admin-panel-title">Latest operational actions</h3>
                            <p class="admin-panel-copy">Recent landlord reviews, property reviews, and inspection status changes from the admin workspace.</p>
                        </div>
                    </div>

                    @if (! $hasRecentActivitySources)
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">Activity data is unavailable in this environment.</h4>
                            <p class="admin-empty-state-copy">This feed will start filling in automatically once the relevant review history tables are available.</p>
                        </div>
                    @elseif ($recentActivity->isEmpty())
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">No review activity has been recorded yet.</h4>
                            <p class="admin-empty-state-copy">As the team updates reviews and inspection requests, the latest actions will show up here.</p>
                        </div>
                    @else
                        <div class="admin-activity-feed">
                            @foreach ($recentActivity as $activity)
                                <div class="admin-activity-row">
                                    <div class="admin-activity-icon">
                                        @if ($activity['type'] === 'Property review')
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 21V7.5L12 3l6.75 4.5V21" />
                                            </svg>
                                        @elseif ($activity['type'] === 'Landlord review')
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 8.25a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 18.75a5.25 5.25 0 0110.5 0" />
                                            </svg>
                                        @else
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75V6m7.5-2.25V6M4.5 8.25h15" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 4.5h10.5A2.25 2.25 0 0119.5 6.75v10.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 17.25V6.75A2.25 2.25 0 016.75 4.5z" />
                                            </svg>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="admin-activity-type">{{ $activity['type'] }}</p>
                                                <p class="admin-activity-title">
                                                    {{ $activity['headline'] }}
                                                    <span class="text-slate-500">for {{ $activity['subject'] }}</span>
                                                </p>
                                            </div>
                                            <span class="admin-activity-time">{{ $activity['timestamp']->diffForHumans() }}</span>
                                        </div>

                                        <p class="admin-activity-meta">Updated by {{ $activity['actor'] }}</p>

                                        @if ($activity['notes'])
                                            <p class="admin-activity-note">{{ $activity['notes'] }}</p>
                                        @endif

                                        @if ($activity['href'])
                                            <a href="{{ $activity['href'] }}" class="admin-inline-link">Open record</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel class="md:col-span-4">
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Landlord verification pipeline</p>
                        <h3 class="admin-panel-title">Current review queue by status</h3>
                        <p class="admin-panel-copy">Monitor where landlord onboarding is bunching up so the queue stays balanced.</p>
                    </div>

                    @if ($landlordPipeline['total'] === 0)
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">No landlord profiles yet.</h4>
                            <p class="admin-empty-state-copy">Verification stages will appear here as soon as landlords start onboarding.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($landlordPipeline['rows'] as $pipelineRow)
                                <div class="space-y-2">
                                    <div class="admin-legend-row">
                                        <div class="flex items-center gap-3">
                                            <span class="admin-legend-dot admin-tone-{{ $pipelineRow['tone'] }}"></span>
                                            <span class="admin-legend-label">{{ $pipelineRow['label'] }}</span>
                                        </div>
                                        <span class="admin-legend-value">{{ $pipelineRow['value'] }}</span>
                                    </div>

                                    <svg class="admin-progress-chart" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true">
                                        <rect class="admin-progress-track" x="0" y="0" width="100" height="8" rx="1.5" />
                                        <rect class="admin-progress-fill admin-tone-{{ $pipelineRow['tone'] }}" x="0" y="0" width="{{ number_format($pipelineRow['width'], 2, '.', '') }}" height="8" rx="1.5" />
                                    </svg>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel class="md:col-span-6">
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Recent inspection outcomes</p>
                        <h3 class="admin-panel-title">Completed visit result mix</h3>
                        <p class="admin-panel-copy">A quick view of what happened after recent visits so the team can spot follow-up patterns and friction points.</p>
                    </div>

                    @if (! $inspectionOutcomes['hasDataSource'])
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">Inspection outcome data is not available yet.</h4>
                            <p class="admin-empty-state-copy">Once inspection tables are available, completed visit outcomes will be grouped here automatically.</p>
                        </div>
                    @elseif ($inspectionOutcomes['total'] === 0)
                        <div class="admin-empty-state">
                            <h4 class="admin-empty-state-title">No completed inspection outcomes yet.</h4>
                            <p class="admin-empty-state-copy">Outcome distribution will appear after visits are completed and outcome types are recorded.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($inspectionOutcomes['rows'] as $outcomeRow)
                                <div class="space-y-2">
                                    <div class="admin-legend-row">
                                        <span class="admin-legend-label">{{ $outcomeRow['label'] }}</span>
                                        <span class="admin-legend-value">{{ $outcomeRow['value'] }}</span>
                                    </div>
                                    <svg class="admin-progress-chart" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true">
                                        <rect class="admin-progress-track" x="0" y="0" width="100" height="8" rx="1.5" />
                                        <rect class="admin-progress-fill admin-tone-sky" x="0" y="0" width="{{ number_format($outcomeRow['width'], 2, '.', '') }}" height="8" rx="1.5" />
                                    </svg>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.panel>

            <x-admin.panel class="md:col-span-6">
                <div class="space-y-5">
                    <div>
                        <p class="admin-eyebrow">Top operational signals</p>
                        <h3 class="admin-panel-title">Queue health and publication focus</h3>
                        <p class="admin-panel-copy">Use these ratios to decide where the next round of admin effort should go.</p>
                    </div>

                    <div class="space-y-4">
                        @foreach ($operationalSignals as $signal)
                            <div class="admin-signal-row">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="admin-signal-label">{{ $signal['label'] }}</p>
                                        <p class="admin-signal-context">{{ $signal['context'] }}</p>
                                    </div>
                                    <span class="admin-signal-value">{{ $signal['value'] }}</span>
                                </div>

                                <svg class="admin-progress-chart" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true">
                                    <rect class="admin-progress-track" x="0" y="0" width="100" height="8" rx="1.5" />
                                    <rect class="admin-progress-fill admin-tone-{{ $signal['tone'] }}" x="0" y="0" width="{{ number_format($signal['width'], 2, '.', '') }}" height="8" rx="1.5" />
                                </svg>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-admin.panel>
        </div>

        <x-admin.panel>
            <div class="space-y-4">
                <div>
                    <p class="admin-eyebrow">Workspace shortcuts</p>
                    <h3 class="admin-panel-title">Operations workspace</h3>
                    <p class="admin-panel-copy">
                        Move directly into landlord reviews, property moderation, and inspection coordination without leaving the dashboard.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.landlords.index') }}" class="admin-button admin-button-primary">
                        Review Landlords
                    </a>
                    <a href="{{ route('admin.properties.index') }}" class="admin-button admin-button-secondary">
                        Review Properties
                    </a>
                    <a href="{{ route('admin.inspection-requests.index') }}" class="admin-button admin-button-success">
                        Manage Inspection Requests
                    </a>
                </div>
            </div>
        </x-admin.panel>
    </div>
</div>
