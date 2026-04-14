<?php

namespace App\Livewire\Tenant;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\InspectionRequest;
use App\Models\Occupancy;
use App\Models\PaymentTransaction;
use App\Models\PropertyPurchase;
use App\Models\TenantProfile;
use App\Support\InspectionRequestOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    use InteractsWithAuthenticatedUser;

    public function render(): View
    {
        $user = $this->currentUser();
        $inspectionRequestsAvailable = $this->hasInspectionRequestsTable();
        $savedListingsAvailable = $this->hasSavedPropertiesTable();
        $savedListingsCount = $savedListingsAvailable ? $user->savedProperties()->count() : 0;
        $inspectionRequestCount = $inspectionRequestsAvailable ? $user->inspectionRequests()->count() : 0;
        $openInspectionRequestCount = $inspectionRequestsAvailable ? $user->inspectionRequests()->open()->count() : 0;
        $closedInspectionRequestCount = $inspectionRequestsAvailable
            ? $user->inspectionRequests()->whereIn('status', [
                InspectionRequestOptions::STATUS_COMPLETED,
                InspectionRequestOptions::STATUS_CANCELLED,
                InspectionRequestOptions::STATUS_REJECTED,
            ])->count()
            : 0;
        $upcomingInspectionRequest = $inspectionRequestsAvailable
            ? $user->inspectionRequests()
                ->with('property')
                ->where('status', InspectionRequestOptions::STATUS_SCHEDULED)
                ->whereNotNull('scheduled_at')
                ->orderBy('scheduled_at')
                ->first()
            : null;
        $latestInspectionRequest = $inspectionRequestsAvailable
            ? $user->inspectionRequests()->with('property')->latest()->first()
            : null;
        $inspectionRequests = $inspectionRequestsAvailable
            ? $user->inspectionRequests()->with('property')->orderedForCoordination()->take(5)->get()
            : new Collection();
        $attentionItems = $inspectionRequestsAvailable
            ? $this->attentionItems($user->getKey(), $upcomingInspectionRequest)
            : [];
        $checklist = $this->onboardingChecklist($user, $inspectionRequestsAvailable);
        $nextActions = $this->nextActions($user, $inspectionRequestsAvailable, $openInspectionRequestCount);

        return view('livewire.tenant.dashboard', [
            'inspectionRequestCount' => $inspectionRequestCount,
            'openInspectionRequestCount' => $openInspectionRequestCount,
            'closedInspectionRequestCount' => $closedInspectionRequestCount,
            'upcomingInspectionRequest' => $upcomingInspectionRequest,
            'latestInspectionRequest' => $latestInspectionRequest,
            'inspectionRequests' => $inspectionRequests,
            'inspectionRequestsAvailable' => $inspectionRequestsAvailable,
            'savedListingsAvailable' => $savedListingsAvailable,
            'savedListingsCount' => $savedListingsCount,
            'attentionItems' => $attentionItems,
            'outcomes' => InspectionRequestOptions::outcomes(),
            'checklist' => $checklist,
            'nextActions' => $nextActions,
        ])->layout('layouts.dashboard-shell', [
            'brandTitle' => 'VerifyHomes Tenant',
            'homeHref' => route('tenant.dashboard'),
            'profileHref' => route('tenant.profile'),
            'roleLabel' => 'Tenant Workspace',
            'navigationLinks' => $this->navigationLinks(),
            'pageHeading' => 'Tenant Dashboard',
            'shellKey' => 'tenant',
            'menuTitle' => 'Workspace Menu',
            'menuCopy' => 'Track your profile, saved listings, payments, scheduled visits, and latest inspection updates from one tenant workspace.',
        ]);
    }

    protected function hasInspectionRequestsTable(): bool
    {
        return Schema::hasTable('inspection_requests');
    }

    protected function hasSavedPropertiesTable(): bool
    {
        return Schema::hasTable('saved_properties');
    }

    protected function navigationLinks(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'href' => route('tenant.dashboard'),
                'active' => request()->routeIs('tenant.dashboard'),
                'icon' => 'dashboard',
            ],
            [
                'label' => 'Profile',
                'href' => route('tenant.profile'),
                'active' => request()->routeIs('tenant.profile'),
                'icon' => 'profile',
            ],
            [
                'label' => 'Saved Listings',
                'href' => route('tenant.saved-listings.index'),
                'active' => request()->routeIs('tenant.saved-listings.*'),
                'icon' => 'properties',
            ],
            [
                'label' => 'Payments',
                'href' => route('tenant.payments.index'),
                'active' => request()->routeIs('tenant.payments.*'),
                'icon' => 'payments',
            ],
            [
                'label' => 'Notifications',
                'href' => route('tenant.notifications.index'),
                'active' => request()->routeIs('tenant.notifications.*'),
                'icon' => 'notifications',
            ],
            [
                'label' => 'My Stays',
                'href' => route('tenant.occupancy.index'),
                'active' => request()->routeIs('tenant.occupancy.*'),
                'icon' => 'occupancy',
            ],
            [
                'label' => 'Inspection Requests',
                'href' => route('tenant.inspection-requests.index'),
                'active' => request()->routeIs('tenant.inspection-requests.*'),
                'icon' => 'inspection-requests',
            ],
            [
                'label' => 'Browse Properties',
                'href' => route('properties.index'),
                'active' => request()->routeIs('properties.*'),
                'icon' => 'browse-properties',
            ],
        ];
    }

    protected function attentionItems(int $tenantId, ?InspectionRequest $upcomingInspectionRequest): array
    {
        $requestedCount = InspectionRequest::query()
            ->forTenant($tenantId)
            ->where('status', InspectionRequestOptions::STATUS_REQUESTED)
            ->count();

        $latestCompletedInspection = InspectionRequest::query()
            ->forTenant($tenantId)
            ->where('status', InspectionRequestOptions::STATUS_COMPLETED)
            ->whereNotNull('outcome_type')
            ->with('property')
            ->latest()
            ->first();

        return collect([
            [
                'label' => 'Requests waiting for scheduling',
                'value' => $requestedCount,
                'context' => 'These requests are still open and waiting for a confirmed visit time.',
                'href' => route('tenant.inspection-requests.index'),
                'cta' => 'View open requests',
            ],
            [
                'label' => 'Upcoming scheduled inspection',
                'value' => $upcomingInspectionRequest ? 1 : 0,
                'context' => $upcomingInspectionRequest
                    ? ($upcomingInspectionRequest->property?->title ?? 'Inspection request').' on '.$upcomingInspectionRequest->scheduled_at?->format('M j, Y g:i A')
                    : '',
                'href' => $upcomingInspectionRequest
                    ? route('tenant.inspection-requests.show', ['inspectionRequestId' => $upcomingInspectionRequest->getKey()])
                    : route('tenant.inspection-requests.index'),
                'cta' => 'View scheduled visit',
            ],
            [
                'label' => 'Latest completed visit outcome',
                'value' => $latestCompletedInspection ? 1 : 0,
                'context' => $latestCompletedInspection
                    ? (($latestCompletedInspection->property?->title ?? 'Inspection request').': '.($latestCompletedInspection->outcomeLabel() ?? 'Outcome available'))
                    : '',
                'href' => $latestCompletedInspection
                    ? route('tenant.inspection-requests.show', ['inspectionRequestId' => $latestCompletedInspection->getKey()])
                    : route('tenant.inspection-requests.index'),
                'cta' => 'Review latest update',
            ],
        ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values()
            ->all();
    }

    protected function onboardingChecklist($user, bool $inspectionRequestsAvailable): array
    {
        $profile = $user->tenantProfile ?: TenantProfile::query()->firstOrCreate(['user_id' => $user->getKey()]);
        $profileComplete = filled($user->phone) && filled($profile->address);
        $inspectionRequested = $inspectionRequestsAvailable
            ? $user->inspectionRequests()->exists()
            : false;
        $paid = Schema::hasTable('payment_transactions')
            ? PaymentTransaction::query()
                ->where('payer_id', $user->getKey())
                ->where('status', 'paid')
                ->whereIn('transaction_type', ['rent_payment', 'house_purchase_payment', 'land_purchase_payment'])
                ->exists()
            : false;
        $trackingStay = Schema::hasTable('occupancies')
            ? Occupancy::query()->where('tenant_id', $user->getKey())->exists()
            : false;

        return [
            [
                'label' => 'Complete profile',
                'complete' => $profileComplete,
                'href' => route('tenant.profile'),
            ],
            [
                'label' => 'Request inspection',
                'complete' => $inspectionRequested,
                'href' => route('properties.index'),
            ],
            [
                'label' => 'Pay for rent or purchase',
                'complete' => $paid,
                'href' => route('tenant.payments.index'),
            ],
            [
                'label' => 'Track your stay',
                'complete' => $trackingStay,
                'href' => route('tenant.occupancy.index'),
            ],
        ];
    }

    protected function nextActions($user, bool $inspectionRequestsAvailable, int $openInspectionRequestCount): array
    {
        $actions = [];

        if (! $inspectionRequestsAvailable) {
            return $actions;
        }

        if ($openInspectionRequestCount > 0) {
            $actions[] = [
                'label' => 'Follow through on inspections',
                'context' => 'Your latest requests are still open or scheduled.',
                'href' => route('tenant.inspection-requests.index'),
                'cta' => 'Open requests',
            ];
        } else {
            $actions[] = [
                'label' => 'Request your next inspection',
                'context' => 'Start with a listing you want to inspect before paying.',
                'href' => route('properties.index'),
                'cta' => 'Browse listings',
            ];
        }

        if (Schema::hasTable('property_purchases')) {
            $hasPurchases = PropertyPurchase::query()->where('buyer_id', $user->getKey())->exists();
            if ($hasPurchases) {
                $actions[] = [
                    'label' => 'Review your purchase receipts',
                    'context' => 'Confirmed purchases live in your receipts list.',
                    'href' => route('tenant.occupancy.index'),
                    'cta' => 'Open receipts',
                ];
            }
        }

        if (Schema::hasTable('occupancies')) {
            $hasOccupancy = Occupancy::query()->where('tenant_id', $user->getKey())->exists();
            $actions[] = [
                'label' => $hasOccupancy ? 'Check your stay status' : 'Track your stay after payment',
                'context' => $hasOccupancy
                    ? 'See due dates, reminders, and move-out actions.'
                    : 'After rent is confirmed, your stay details will appear here.',
                'href' => route('tenant.occupancy.index'),
                'cta' => 'Open My Stays',
            ];
        }

        return $actions;
    }
}
