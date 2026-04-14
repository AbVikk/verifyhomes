<?php

namespace App\Livewire\Landlord\Properties;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\LandlordProfile;
use App\Models\Property;
use App\Support\Currency;
use App\Support\InspectionRequestOptions;
use App\Support\PublicPropertyVisibility;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public function render(): View
    {
        $profile = LandlordProfile::query()->firstOrCreate(
            ['user_id' => $this->currentUserId()],
            [
                'verification_status' => 'pending',
                'city' => 'Akure',
                'state' => 'Ondo',
            ],
        );
        $propertiesQuery = $this->currentUser()->landlordProperties();

        $properties = (clone $propertiesQuery)
            ->with(['coverImage'])
            ->withCount([
                'images',
                'documents',
                'inspectionRequests as open_inspection_requests_count' => fn ($query) => $query->open(),
                'inspectionRequests as scheduled_inspection_requests_count' => fn ($query) => $query->where('status', InspectionRequestOptions::STATUS_SCHEDULED),
                'purchases as purchase_count',
            ])
            ->latest()
            ->get();

        $totalPropertiesCount = (clone $propertiesQuery)->count();
        $pendingReviewPropertiesCount = (clone $propertiesQuery)->where('status', 'pending_review')->count();
        $approvedUnpublishedPropertiesCount = (clone $propertiesQuery)
            ->where('status', PublicPropertyVisibility::APPROVED_STATUS)
            ->where('is_verified', true)
            ->where('is_published', false)
            ->count();
        $livePublishedPropertiesCount = (clone $propertiesQuery)->publiclyVisible()->count();
        $needsAttentionPropertiesCount = $properties->filter(fn (Property $property) => $this->needsAttention($property))->count();

        return view('livewire.landlord.properties.index', [
            'properties' => $properties,
            'canCreateProperties' => $profile->canCreateProperties(),
            'propertyCreationBlockMessage' => $profile->propertyCreationBlockMessage(),
            'totalPropertiesCount' => $totalPropertiesCount,
            'pendingReviewPropertiesCount' => $pendingReviewPropertiesCount,
            'approvedUnpublishedPropertiesCount' => $approvedUnpublishedPropertiesCount,
            'livePublishedPropertiesCount' => $livePublishedPropertiesCount,
            'needsAttentionPropertiesCount' => $needsAttentionPropertiesCount,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('My Properties'));
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        return Currency::format($amount);
    }

    public function nextStepSummary(Property $property): string
    {
        if ($property->open_inspection_requests_count > 0) {
            return 'Tenant activity is already open on this listing. Review the request queue and keep access details current.';
        }

        if ($property->status === 'pending_review') {
            return 'This listing is waiting for admin review. Open it if you need to correct details or supporting files.';
        }

        if ($property->status === PublicPropertyVisibility::APPROVED_STATUS && $property->is_verified && ! $property->is_published) {
            return 'This listing is approved but still not public. Recheck the details so you know it is ready for visibility.';
        }

        if ($property->isPubliclyVisible()) {
            return 'This listing is live. Watch for fresh tenant requests and keep the property inspection-ready.';
        }

        return 'Open this listing to review its details and keep the record current.';
    }

    public function needsAttention(Property $property): bool
    {
        return $property->status === 'pending_review'
            || ($property->status === PublicPropertyVisibility::APPROVED_STATUS && $property->is_verified && ! $property->is_published)
            || $property->open_inspection_requests_count > 0
            || $property->images_count === 0
            || $property->documents_count === 0;
    }
}
