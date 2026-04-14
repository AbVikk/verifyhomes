<?php

namespace App\Livewire\Landlord;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\InspectionRequest;
use App\Models\LandlordDocument;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Support\InspectionRequestOptions;
use App\Support\PublicPropertyVisibility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    use InteractsWithAuthenticatedUser;

    public function render(): View
    {
        $documentsAvailable = $this->hasLandlordDocumentsTable();
        $inspectionRequestsAvailable = $this->hasInspectionRequestsTable();
        $user = $this->currentUser();
        $profile = LandlordProfile::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'verification_status' => 'pending',
                'city' => 'Akure',
                'state' => 'Ondo',
            ],
        );
        $latestDocument = null;
        $documentCount = 0;

        if ($documentsAvailable) {
            $documentsQuery = LandlordDocument::query()
                ->where('landlord_profile_id', $profile->getKey());

            $latestDocument = (clone $documentsQuery)
                ->with('reviewer')
                ->latest('created_at')
                ->first();
            $documentCount = (clone $documentsQuery)->count();
        }

        $landlordProperties = Property::query()->where('landlord_id', $user->getKey());

        $propertyCount = (clone $landlordProperties)->count();
        $pendingReviewPropertiesCount = (clone $landlordProperties)->where('status', 'pending_review')->count();
        $approvedUnpublishedPropertiesCount = (clone $landlordProperties)
            ->where('status', PublicPropertyVisibility::APPROVED_STATUS)
            ->where('is_verified', true)
            ->where('is_published', false)
            ->count();
        $livePublishedPropertiesCount = PublicPropertyVisibility::apply(clone $landlordProperties)->count();
        $recentPropertiesQuery = (clone $landlordProperties)->latest('updated_at');

        if ($inspectionRequestsAvailable) {
            $recentPropertiesQuery->withCount([
                'inspectionRequests as open_inspection_requests_count' => fn ($query) => $query->open(),
                'inspectionRequests as scheduled_inspection_requests_count' => fn ($query) => $query->where('status', InspectionRequestOptions::STATUS_SCHEDULED),
            ]);
        }

        $recentProperties = $recentPropertiesQuery
            ->take(3)
            ->get()
            ->each(function (Property $property) use ($inspectionRequestsAvailable): void {
                if ($inspectionRequestsAvailable) {
                    return;
                }

                $property->open_inspection_requests_count = 0;
                $property->scheduled_inspection_requests_count = 0;
            });

        $openInspectionRequestCount = $inspectionRequestsAvailable
            ? InspectionRequest::query()->forLandlord($user->getKey())->open()->count()
            : 0;
        $scheduledInspectionRequestCount = $inspectionRequestsAvailable
            ? InspectionRequest::query()->forLandlord($user->getKey())->where('status', InspectionRequestOptions::STATUS_SCHEDULED)->count()
            : 0;
        $requestedInspectionRequestCount = $inspectionRequestsAvailable
            ? InspectionRequest::query()->forLandlord($user->getKey())->where('status', InspectionRequestOptions::STATUS_REQUESTED)->count()
            : 0;
        $recentInspectionRequests = $inspectionRequestsAvailable
            ? InspectionRequest::query()
                ->forLandlord($user->getKey())
                ->with('property')
                ->orderedForCoordination()
                ->take(3)
                ->get()
            : new Collection();
        $nextInspectionRequest = $inspectionRequestsAvailable
            ? InspectionRequest::query()
                ->forLandlord($user->getKey())
                ->with('property')
                ->orderedForCoordination()
                ->first()
            : null;
        $completionChecks = [
            filled($user->phone),
            filled($profile?->business_name),
            filled($profile?->address),
            filled($profile?->city),
            filled($profile?->state),
        ];

        $completedItems = collect($completionChecks)->filter()->count();
        $completionPercentage = (int) round(($completedItems / count($completionChecks)) * 100);
        $attentionItems = $this->attentionItems(
            $completionPercentage,
            $documentCount,
            $documentsAvailable,
            $pendingReviewPropertiesCount,
            $approvedUnpublishedPropertiesCount,
            $openInspectionRequestCount,
        );
        $recommendedActions = $this->recommendedActions(
            $profile,
            $documentsAvailable,
            $documentCount,
            $propertyCount,
            $pendingReviewPropertiesCount,
            $approvedUnpublishedPropertiesCount,
            $nextInspectionRequest,
        );
        $checklist = $this->onboardingChecklist(
            $completionPercentage,
            $documentsAvailable,
            $documentCount,
            $propertyCount,
        );
        $nextActions = $recommendedActions;

        return view('livewire.landlord.dashboard', [
            'profile' => $profile,
            'completionPercentage' => $completionPercentage,
            'documentCount' => $documentCount,
            'documentsAvailable' => $documentsAvailable,
            'latestDocument' => $latestDocument,
            'canCreateProperties' => $profile->canCreateProperties(),
            'propertyCreationBlockMessage' => $profile->propertyCreationBlockMessage(),
            'propertyCount' => $propertyCount,
            'pendingReviewPropertiesCount' => $pendingReviewPropertiesCount,
            'approvedUnpublishedPropertiesCount' => $approvedUnpublishedPropertiesCount,
            'livePublishedPropertiesCount' => $livePublishedPropertiesCount,
            'openInspectionRequestCount' => $openInspectionRequestCount,
            'scheduledInspectionRequestCount' => $scheduledInspectionRequestCount,
            'requestedInspectionRequestCount' => $requestedInspectionRequestCount,
            'inspectionRequestsAvailable' => $inspectionRequestsAvailable,
            'recentProperties' => $recentProperties,
            'recentInspectionRequests' => $recentInspectionRequests,
            'attentionItems' => $attentionItems,
            'recommendedActions' => $recommendedActions,
            'checklist' => $checklist,
            'nextActions' => $nextActions,
        ])->layout('layouts.dashboard-shell', [
            'brandTitle' => 'VerifyHomes Landlord',
            'homeHref' => route('landlord.dashboard'),
            'roleLabel' => 'Landlord Workspace',
            'navigationLinks' => $this->navigationLinks(),
            'pageHeading' => 'Landlord Dashboard',
            'shellKey' => 'landlord',
            'menuTitle' => 'Workspace Menu',
            'menuCopy' => 'Track your listing pipeline, document readiness, payments, and inspection coordination from one landlord workspace.',
        ]);
    }

    public function propertyActionSummary(Property $property): string
    {
        if ($property->open_inspection_requests_count > 0) {
            return 'This listing already has tenant activity. Review the request queue and keep access details current.';
        }

        if ($property->status === 'pending_review') {
            return 'This listing is still waiting for admin review. Open it if you need to tighten the details or supporting files.';
        }

        if ($property->status === PublicPropertyVisibility::APPROVED_STATUS && $property->is_verified && ! $property->is_published) {
            return 'This listing is approved but not yet public. Recheck the listing details and follow up if visibility should change.';
        }

        if ($property->isPubliclyVisible()) {
            return 'This listing is live. Keep an eye on incoming requests and make sure the property stays inspection-ready.';
        }

        return 'Open this listing to review its details and keep the record current.';
    }

    public function inspectionRequestActionSummary(InspectionRequest $inspectionRequest): string
    {
        return match ($inspectionRequest->status) {
            InspectionRequestOptions::STATUS_REQUESTED => 'Admin may still need access or readiness details before scheduling.',
            InspectionRequestOptions::STATUS_SCHEDULED => 'Admin has scheduled the visit. Keep access and the property ready.',
            InspectionRequestOptions::STATUS_COMPLETED => 'The visit is done. Review the outcome and any follow-up notes.',
            InspectionRequestOptions::STATUS_CANCELLED, InspectionRequestOptions::STATUS_REJECTED => 'This request is closed. Open it if you need the final notes.',
            default => 'Open the request for the latest admin update.',
        };
    }

    protected function hasInspectionRequestsTable(): bool
    {
        return Schema::hasTable('inspection_requests');
    }

    protected function hasLandlordDocumentsTable(): bool
    {
        return Schema::hasTable('landlord_documents');
    }

    protected function navigationLinks(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'href' => route('landlord.dashboard'),
                'active' => request()->routeIs('landlord.dashboard'),
                'icon' => 'dashboard',
            ],
            [
                'label' => 'Profile',
                'href' => route('landlord.profile'),
                'active' => request()->routeIs('landlord.profile'),
                'icon' => 'profile',
            ],
            [
                'label' => 'Documents',
                'href' => route('landlord.documents'),
                'active' => request()->routeIs('landlord.documents'),
                'icon' => 'documents',
            ],
            [
                'label' => 'Properties',
                'href' => route('landlord.properties'),
                'active' => request()->routeIs('landlord.properties.*'),
                'icon' => 'properties',
            ],
            [
                'label' => 'Inspection Requests',
                'href' => route('landlord.inspection-requests.index'),
                'active' => request()->routeIs('landlord.inspection-requests.*'),
                'icon' => 'inspection-requests',
            ],
            [
                'label' => 'Payments',
                'href' => route('landlord.payments.index'),
                'active' => request()->routeIs('landlord.payments.*'),
                'icon' => 'payments',
            ],
            [
                'label' => 'Notifications',
                'href' => route('landlord.notifications.index'),
                'active' => request()->routeIs('landlord.notifications.*'),
                'icon' => 'notifications',
            ],
            [
                'label' => 'Occupants',
                'href' => route('landlord.occupancy.index'),
                'active' => request()->routeIs('landlord.occupancy.*'),
                'icon' => 'occupancy',
            ],
        ];
    }

    protected function attentionItems(
        int $completionPercentage,
        int $documentCount,
        bool $documentsAvailable,
        int $pendingReviewPropertiesCount,
        int $approvedUnpublishedPropertiesCount,
        int $openInspectionRequestCount,
    ): array {
        return collect([
            [
                'label' => 'Complete your landlord profile',
                'value' => $completionPercentage < 100 ? 100 - $completionPercentage : 0,
                'context' => 'Finish the remaining profile fields so your onboarding stays easy to review.',
                'href' => route('landlord.profile'),
                'cta' => 'Complete profile',
            ],
            [
                'label' => 'Upload verification documents',
                'value' => $documentsAvailable && $documentCount === 0 ? 1 : 0,
                'context' => 'Your verification queue stays blocked until at least one document is uploaded.',
                'href' => route('landlord.documents'),
                'cta' => 'Upload documents',
            ],
            [
                'label' => 'Listings waiting in review',
                'value' => $pendingReviewPropertiesCount,
                'context' => 'These properties are still waiting for an admin decision.',
                'href' => route('landlord.properties'),
                'cta' => 'Review listings',
            ],
            [
                'label' => 'Approved listings ready to publish',
                'value' => $approvedUnpublishedPropertiesCount,
                'context' => 'These listings are approved but still not public, so they need a quick landlord check.',
                'href' => route('landlord.properties'),
                'cta' => 'Check approved listings',
            ],
            [
                'label' => 'Inspection requests need awareness',
                'value' => $openInspectionRequestCount,
                'context' => 'Requested and scheduled visits still need coordination from your side.',
                'href' => route('landlord.inspection-requests.index'),
                'cta' => 'Open inspection requests',
            ],
        ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values()
            ->all();
    }

    protected function onboardingChecklist(
        int $completionPercentage,
        bool $documentsAvailable,
        int $documentCount,
        int $propertyCount,
    ): array {
        return [
            [
                'label' => 'Complete profile',
                'complete' => $completionPercentage >= 100,
                'href' => route('landlord.profile'),
            ],
            [
                'label' => 'Upload documents',
                'complete' => $documentsAvailable && $documentCount > 0,
                'href' => route('landlord.documents'),
            ],
            [
                'label' => 'Create your first listing',
                'complete' => $propertyCount > 0,
                'href' => route('landlord.properties.create'),
            ],
        ];
    }

    protected function recommendedActions(
        LandlordProfile $profile,
        bool $documentsAvailable,
        int $documentCount,
        int $propertyCount,
        int $pendingReviewPropertiesCount,
        int $approvedUnpublishedPropertiesCount,
        ?InspectionRequest $nextInspectionRequest,
    ): array {
        return collect([
            ! $profile->canCreateProperties()
                ? [
                    'label' => 'Unlock property creation',
                    'context' => $profile->propertyCreationBlockMessage(),
                    'href' => $documentsAvailable ? route('landlord.documents') : route('landlord.profile'),
                    'cta' => $documentsAvailable ? 'Open documents' : 'Open profile',
                ]
                : null,
            $documentsAvailable && $documentCount === 0
                ? [
                    'label' => 'Upload your first verification document',
                    'context' => 'A document upload helps move your landlord review forward and keeps the listing workflow unblocked.',
                    'href' => route('landlord.documents'),
                    'cta' => 'Upload document',
                ]
                : null,
            $propertyCount === 0 && $profile->canCreateProperties()
                ? [
                    'label' => 'Create your first property',
                    'context' => 'Your workspace is ready. Add your first listing so review and discovery can begin.',
                    'href' => route('landlord.properties.create'),
                    'cta' => 'Create property',
                ]
                : null,
            $pendingReviewPropertiesCount > 0
                ? [
                    'label' => 'Review listings waiting on admin',
                    'context' => 'Open the property queue and confirm the listings in review still have the right details and supporting files.',
                    'href' => route('landlord.properties'),
                    'cta' => 'Open property queue',
                ]
                : null,
            $approvedUnpublishedPropertiesCount > 0
                ? [
                    'label' => 'Check approved listings',
                    'context' => 'Some listings are approved but not visible yet. Recheck them so you know what should go live next.',
                    'href' => route('landlord.properties'),
                    'cta' => 'Review approved listings',
                ]
                : null,
            $nextInspectionRequest
                ? [
                    'label' => 'Follow through on the latest inspection request',
                    'context' => 'The next coordination item is '.$this->inspectionRequestActionSummary($nextInspectionRequest),
                    'href' => route('landlord.inspection-requests.show', ['inspectionRequestId' => $nextInspectionRequest->getKey()]),
                    'cta' => 'Open latest request',
                ]
                : null,
        ])
            ->filter()
            ->values()
            ->all();
    }
}
