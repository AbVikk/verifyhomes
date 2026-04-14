<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\InspectionRequest;
use App\Models\InspectionRequestStatusHistory;
use App\Models\LandlordProfile;
use App\Models\LandlordStatusHistory;
use App\Models\OccupancyComplaint;
use App\Models\OccupancyMoveOutRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use App\Support\InspectionRequestOptions;
use App\Support\Currency;
use App\Support\PublicPropertyVisibility;
use App\Support\ReviewStatusOptions;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class Dashboard extends Component
{
    use HasAdminLayout;

    public function render(): View
    {
        $tableAvailability = $this->tableAvailability();

        $landlordStatusCounts = LandlordProfile::query()
            ->selectRaw('verification_status, COUNT(*) as aggregate')
            ->groupBy('verification_status')
            ->pluck('aggregate', 'verification_status');

        $propertyStatusCounts = Property::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $approvedPropertiesCount = (int) ($propertyStatusCounts['approved'] ?? 0);
        $approvedUnpublishedPropertiesCount = Property::query()
            ->where('status', PublicPropertyVisibility::APPROVED_STATUS)
            ->where('is_verified', true)
            ->where('is_published', false)
            ->count();
        $livePublishedPropertiesCount = PublicPropertyVisibility::apply(Property::query())->count();

        $requestedInspectionCount = $tableAvailability['inspection_requests']
            ? InspectionRequest::query()->whereIn('status', [
                InspectionRequestOptions::STATUS_REQUESTED,
                InspectionRequestOptions::STATUS_SCHEDULED,
            ])->count()
            : 0;

        $closedInspectionCount = $tableAvailability['inspection_requests']
            ? InspectionRequest::query()->whereIn('status', [
                InspectionRequestOptions::STATUS_COMPLETED,
                InspectionRequestOptions::STATUS_CANCELLED,
                InspectionRequestOptions::STATUS_REJECTED,
            ])->count()
            : 0;

        $pendingLandlordsCount = (int) (($landlordStatusCounts['pending'] ?? 0) + ($landlordStatusCounts['under_review'] ?? 0));
        $rejectedOrSuspendedLandlordsCount = (int) (($landlordStatusCounts['rejected'] ?? 0) + ($landlordStatusCounts['suspended'] ?? 0));
        $pendingPropertiesCount = (int) ($propertyStatusCounts['pending_review'] ?? 0);
        $rejectedOrSuspendedPropertiesCount = (int) (($propertyStatusCounts['rejected'] ?? 0) + ($propertyStatusCounts['suspended'] ?? 0));
        $inspectionTrend = $this->inspectionTrend($tableAvailability['inspection_requests']);
        $publishReadiness = $this->publishReadiness(
            $propertyStatusCounts,
            $approvedUnpublishedPropertiesCount,
            $livePublishedPropertiesCount,
        );
        $recentActivity = $this->recentActivity($tableAvailability);
        $landlordPipeline = $this->landlordPipeline($landlordStatusCounts);
        $inspectionOutcomes = $this->inspectionOutcomes($tableAvailability['inspection_requests']);
        $attentionItems = $this->attentionItems(
            $pendingLandlordsCount,
            $pendingPropertiesCount,
            $approvedUnpublishedPropertiesCount,
            $requestedInspectionCount,
        );
        $moveOutPendingCount = $tableAvailability['occupancy_move_out_requests']
            ? OccupancyMoveOutRequest::query()->where('status', 'pending')->count()
            : 0;
        $openComplaintCount = $tableAvailability['occupancy_complaints']
            ? OccupancyComplaint::query()->whereIn('status', ['open', 'in_review'])->count()
            : 0;
        $checklist = $this->onboardingChecklist(
            $pendingLandlordsCount,
            $pendingPropertiesCount,
            $moveOutPendingCount,
            $openComplaintCount,
            $tableAvailability['occupancy_move_out_requests'] || $tableAvailability['occupancy_complaints'],
        );
        $nextActions = $attentionItems;
        $paymentSummary = $this->paymentSummary($tableAvailability['payment_transactions']);
        $operationalSignals = $this->operationalSignals(
            (int) $landlordStatusCounts->sum(),
            $approvedPropertiesCount,
            $approvedUnpublishedPropertiesCount,
            $livePublishedPropertiesCount,
            $pendingLandlordsCount,
            $requestedInspectionCount,
            $closedInspectionCount,
        );

        return $this->adminPage(view('livewire.admin.dashboard', [
            'pendingLandlordsCount' => $pendingLandlordsCount,
            'approvedLandlordsCount' => (int) ($landlordStatusCounts['approved'] ?? 0),
            'rejectedOrSuspendedLandlordsCount' => $rejectedOrSuspendedLandlordsCount,
            'pendingPropertiesCount' => $pendingPropertiesCount,
            'approvedPropertiesCount' => $approvedPropertiesCount,
            'approvedUnpublishedPropertiesCount' => $approvedUnpublishedPropertiesCount,
            'livePublishedPropertiesCount' => $livePublishedPropertiesCount,
            'rejectedOrSuspendedPropertiesCount' => $rejectedOrSuspendedPropertiesCount,
            'requestedInspectionCount' => $requestedInspectionCount,
            'closedInspectionCount' => $closedInspectionCount,
            'inspectionTrend' => $inspectionTrend,
            'publishReadiness' => $publishReadiness,
            'recentActivity' => $recentActivity['items'],
            'hasRecentActivitySources' => $recentActivity['hasAvailableSources'],
            'landlordPipeline' => $landlordPipeline,
            'inspectionOutcomes' => $inspectionOutcomes,
            'attentionItems' => $attentionItems,
            'checklist' => $checklist,
            'nextActions' => $nextActions,
            'paymentSummary' => $paymentSummary,
            'operationalSignals' => $operationalSignals,
            'hasInspectionRequestData' => $tableAvailability['inspection_requests'],
        ]), 'Admin Dashboard');
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    private function tableAvailability(): array
    {
        return [
            'inspection_requests' => $this->hasTable('inspection_requests'),
            'inspection_request_status_histories' => $this->hasTable('inspection_request_status_histories'),
            'property_status_histories' => $this->hasTable('property_status_histories'),
            'landlord_status_histories' => $this->hasTable('landlord_status_histories'),
            'payment_transactions' => $this->hasTable('payment_transactions'),
            'occupancy_move_out_requests' => $this->hasTable('occupancy_move_out_requests'),
            'occupancy_complaints' => $this->hasTable('occupancy_complaints'),
        ];
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function inspectionTrend(bool $hasInspectionRequestsTable): array
    {
        $startDate = CarbonImmutable::today()->subDays(13);
        $dateRange = collect(range(0, 13))->map(fn (int $offset) => $startDate->addDays($offset));

        if (! $hasInspectionRequestsTable) {
            return [
                'hasDataSource' => false,
                'total' => 0,
                'labels' => $dateRange->map(fn (CarbonImmutable $date) => $date->format('M j'))->all(),
                'points' => $dateRange->map(fn () => 0)->all(),
                'requested' => 0,
                'scheduled' => 0,
                'completed' => 0,
            ];
        }

        $counts = InspectionRequest::query()
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->groupBy('activity_date')
            ->pluck('aggregate', 'activity_date');

        return [
            'hasDataSource' => true,
            'total' => (int) $counts->sum(),
            'labels' => $dateRange->map(fn (CarbonImmutable $date) => $date->format('M j'))->all(),
            'points' => $dateRange->map(fn (CarbonImmutable $date) => (int) ($counts[$date->toDateString()] ?? 0))->all(),
            'requested' => InspectionRequest::query()->where('status', InspectionRequestOptions::STATUS_REQUESTED)->count(),
            'scheduled' => InspectionRequest::query()->where('status', InspectionRequestOptions::STATUS_SCHEDULED)->count(),
            'completed' => InspectionRequest::query()->where('status', InspectionRequestOptions::STATUS_COMPLETED)->count(),
        ];
    }

    private function publishReadiness(Collection $propertyStatusCounts, int $approvedUnpublishedPropertiesCount, int $livePublishedPropertiesCount): array
    {
        $segments = collect([
            [
                'label' => 'Pending review',
                'value' => (int) ($propertyStatusCounts['pending_review'] ?? 0),
                'tone' => 'slate',
            ],
            [
                'label' => 'Approved, unpublished',
                'value' => $approvedUnpublishedPropertiesCount,
                'tone' => 'sky',
            ],
            [
                'label' => 'Published live',
                'value' => $livePublishedPropertiesCount,
                'tone' => 'emerald',
            ],
            [
                'label' => 'Rejected or suspended',
                'value' => (int) (($propertyStatusCounts['rejected'] ?? 0) + ($propertyStatusCounts['suspended'] ?? 0)),
                'tone' => 'amber',
            ],
        ])->values();

        return [
            'total' => $segments->sum('value'),
            'segments' => $segments->all(),
        ];
    }

    private function recentActivity(array $tableAvailability): array
    {
        $activitySources = collect();

        if ($tableAvailability['property_status_histories']) {
            $activitySources = $activitySources->merge(
                PropertyStatusHistory::query()
                    ->with(['property:id,title,slug', 'changedBy:id,name'])
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(function (PropertyStatusHistory $history): array {
                        return [
                            'type' => 'Property review',
                            'headline' => str($history->to_status)->headline(),
                            'subject' => $history->property?->title ?? 'Property record',
                            'actor' => $history->changedBy?->name ?? 'Admin team',
                            'timestamp' => $history->created_at,
                            'notes' => $history->notes,
                            'href' => $history->property ? route('admin.properties.show', $history->property) : null,
                        ];
                    })
            );
        }

        if ($tableAvailability['landlord_status_histories']) {
            $activitySources = $activitySources->merge(
                LandlordStatusHistory::query()
                    ->with(['landlordProfile.user:id,name', 'changedBy:id,name'])
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(function (LandlordStatusHistory $history): array {
                        return [
                            'type' => 'Landlord review',
                            'headline' => str($history->to_status)->headline(),
                            'subject' => $history->landlordProfile?->user?->name ?? 'Landlord profile',
                            'actor' => $history->changedBy?->name ?? 'Admin team',
                            'timestamp' => $history->created_at,
                            'notes' => $history->notes,
                            'href' => $history->landlordProfile ? route('admin.landlords.show', $history->landlordProfile) : null,
                        ];
                    })
            );
        }

        $includeInspectionActivity = $tableAvailability['inspection_requests']
            && $tableAvailability['inspection_request_status_histories'];

        if ($includeInspectionActivity) {
            $activitySources = $activitySources->merge(
                InspectionRequestStatusHistory::query()
                    ->with(['inspectionRequest.property:id,title,slug', 'changedBy:id,name'])
                    ->latest()
                    ->take(5)
                    ->get()
                    ->map(function (InspectionRequestStatusHistory $history): array {
                        return [
                            'type' => 'Inspection request',
                            'headline' => str($history->to_status)->headline(),
                            'subject' => $history->inspectionRequest?->property?->title ?? 'Inspection request',
                            'actor' => $history->changedBy?->name ?? 'System',
                            'timestamp' => $history->created_at,
                            'notes' => $history->notes,
                            'href' => $history->inspectionRequest
                                ? route('admin.inspection-requests.show', ['inspectionRequestId' => $history->inspectionRequest->getKey()])
                                : null,
                        ];
                    })
            );
        }

        return [
            'items' => $activitySources
                ->sortByDesc('timestamp')
                ->take(7)
                ->values(),
            'hasAvailableSources' => $tableAvailability['property_status_histories']
                || $tableAvailability['landlord_status_histories']
                || $includeInspectionActivity,
        ];
    }

    private function landlordPipeline(Collection $landlordStatusCounts): array
    {
        $rows = collect(ReviewStatusOptions::landlordStatuses())
            ->map(function (string $label, string $status) use ($landlordStatusCounts): array {
                return [
                    'label' => $label,
                    'value' => (int) ($landlordStatusCounts[$status] ?? 0),
                    'tone' => match ($status) {
                        'approved' => 'emerald',
                        'rejected', 'suspended' => 'amber',
                        'under_review' => 'sky',
                        default => 'slate',
                    },
                ];
            })
            ->values();

        $total = $rows->sum('value');

        $rows = $rows
            ->map(function (array $row) use ($total): array {
                $row['width'] = $total > 0 ? ($row['value'] / $total) * 100 : 0;

                return $row;
            })
            ->values();

        return [
            'total' => $total,
            'rows' => $rows->all(),
        ];
    }

    private function inspectionOutcomes(bool $hasInspectionRequestsTable): array
    {
        if (! $hasInspectionRequestsTable) {
            return [
                'total' => 0,
                'rows' => [],
                'hasDataSource' => false,
            ];
        }

        $counts = InspectionRequest::query()
            ->where('status', InspectionRequestOptions::STATUS_COMPLETED)
            ->whereNotNull('outcome_type')
            ->selectRaw('outcome_type, COUNT(*) as aggregate')
            ->groupBy('outcome_type')
            ->pluck('aggregate', 'outcome_type');

        $rows = collect(InspectionRequestOptions::outcomes())
            ->map(function (string $label, string $outcomeType) use ($counts): array {
                return [
                    'label' => $label,
                    'value' => (int) ($counts[$outcomeType] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['value'] > 0)
            ->sortByDesc('value')
            ->values();

        $total = $rows->sum('value');

        $rows = $rows
            ->map(function (array $row) use ($total): array {
                $row['width'] = $total > 0 ? ($row['value'] / $total) * 100 : 0;

                return $row;
            })
            ->values();

        return [
            'total' => $total,
            'rows' => $rows->all(),
            'hasDataSource' => true,
        ];
    }

    private function attentionItems(
        int $pendingLandlordsCount,
        int $pendingPropertiesCount,
        int $approvedUnpublishedPropertiesCount,
        int $requestedInspectionCount,
    ): array {
        return collect([
            [
                'label' => 'Landlord reviews waiting for a decision',
                'value' => $pendingLandlordsCount,
                'context' => 'Clear the onboarding queue for pending and under-review landlord profiles.',
                'href' => route('admin.landlords.index'),
                'cta' => 'Review landlords',
            ],
            [
                'label' => 'Property submissions waiting in review',
                'value' => $pendingPropertiesCount,
                'context' => 'Moderate listings that are still pending before they can move forward.',
                'href' => route('admin.properties.index'),
                'cta' => 'Review properties',
            ],
            [
                'label' => 'Approved listings waiting to go live',
                'value' => $approvedUnpublishedPropertiesCount,
                'context' => 'These properties are verified and approved but still unpublished.',
                'href' => route('admin.properties.index'),
                'cta' => 'Open publish queue',
            ],
            [
                'label' => 'Inspection requests need coordination',
                'value' => $requestedInspectionCount,
                'context' => 'Requested and scheduled visits still need active follow-through.',
                'href' => route('admin.inspection-requests.index'),
                'cta' => 'Manage inspections',
            ],
        ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values()
            ->all();
    }

    private function operationalSignals(
        int $totalLandlords,
        int $approvedPropertiesCount,
        int $approvedUnpublishedPropertiesCount,
        int $livePublishedPropertiesCount,
        int $pendingLandlordsCount,
        int $requestedInspectionCount,
        int $closedInspectionCount,
    ): array {
        $totalInspections = $requestedInspectionCount + $closedInspectionCount;

        return collect([
            [
                'label' => 'Publish-ready inventory',
                'value' => $approvedUnpublishedPropertiesCount,
                'context' => $approvedPropertiesCount > 0
                    ? $this->percentageLabel($approvedUnpublishedPropertiesCount, $approvedPropertiesCount).' of approved listings'
                    : 'No approved listings yet',
                'ratio' => $approvedPropertiesCount > 0 ? $approvedUnpublishedPropertiesCount / $approvedPropertiesCount : 0,
                'tone' => 'sky',
            ],
            [
                'label' => 'Live property visibility',
                'value' => $livePublishedPropertiesCount,
                'context' => $approvedPropertiesCount > 0
                    ? $this->percentageLabel($livePublishedPropertiesCount, $approvedPropertiesCount).' of approved listings'
                    : 'No approved listings yet',
                'ratio' => $approvedPropertiesCount > 0 ? $livePublishedPropertiesCount / $approvedPropertiesCount : 0,
                'tone' => 'emerald',
            ],
            [
                'label' => 'Landlords awaiting decisions',
                'value' => $pendingLandlordsCount,
                'context' => $totalLandlords > 0
                    ? $this->percentageLabel($pendingLandlordsCount, $totalLandlords).' of landlord profiles'
                    : 'No landlord profiles yet',
                'ratio' => $totalLandlords > 0 ? $pendingLandlordsCount / $totalLandlords : 0,
                'tone' => 'amber',
            ],
            [
                'label' => 'Inspection closure rate',
                'value' => $closedInspectionCount,
                'context' => $totalInspections > 0
                    ? $this->percentageLabel($closedInspectionCount, $totalInspections).' resolved overall'
                    : 'No inspection activity yet',
                'ratio' => $totalInspections > 0 ? $closedInspectionCount / $totalInspections : 0,
                'tone' => 'slate',
            ],
        ])
            ->map(function (array $signal): array {
                $signal['width'] = $signal['ratio'] * 100;

                return $signal;
            })
            ->all();
    }

    private function paymentSummary(bool $hasPaymentTransactionsTable): array
    {
        if (! $hasPaymentTransactionsTable) {
            return [
                'hasDataSource' => false,
                'paidTransactionsCount' => 0,
                'grossAmount' => 0,
                'platformFeeAmount' => 0,
                'netAmount' => 0,
                'configuredPercentage' => (float) config('payments.platform_fee_percentage', 10),
            ];
        }

        $paidTransactions = PaymentTransaction::query()->where('status', 'paid');

        return [
            'hasDataSource' => true,
            'paidTransactionsCount' => (clone $paidTransactions)->count(),
            'grossAmount' => (float) ((clone $paidTransactions)->sum('gross_amount') ?: 0),
            'platformFeeAmount' => (float) ((clone $paidTransactions)->sum('platform_fee_amount') ?: 0),
            'netAmount' => (float) ((clone $paidTransactions)->sum('net_amount') ?: 0),
            'configuredPercentage' => (float) config('payments.platform_fee_percentage', 10),
        ];
    }

    private function percentageLabel(int $part, int $whole): string
    {
        return (string) round(($part / max($whole, 1)) * 100).'%';
    }

    private function onboardingChecklist(
        int $pendingLandlordsCount,
        int $pendingPropertiesCount,
        int $pendingMoveOutCount,
        int $openComplaintCount,
        bool $occupancyDataAvailable,
    ): array {
        return [
            [
                'label' => 'Review landlord documents',
                'complete' => $pendingLandlordsCount === 0,
                'href' => route('admin.landlords.index'),
            ],
            [
                'label' => 'Review listings',
                'complete' => $pendingPropertiesCount === 0,
                'href' => route('admin.properties.index'),
            ],
            [
                'label' => 'Manage occupancies',
                'complete' => $occupancyDataAvailable && ($pendingMoveOutCount + $openComplaintCount) === 0,
                'href' => route('admin.occupancy.index'),
            ],
        ];
    }
}
