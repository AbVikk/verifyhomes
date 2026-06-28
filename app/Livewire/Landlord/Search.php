<?php

namespace App\Livewire\Landlord;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\InspectionRequest;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;

class Search extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    #[Url(except: '')]
    public string $q = '';

    public function render()
    {
        $query = trim($this->q);
        $normalized = mb_strtolower($query);
        $like = '%'.$query.'%';
        $results = [
            'properties' => new Collection(),
            'tenants' => new Collection(),
            'payments' => new Collection(),
        ];

        if (mb_strlen($query) >= 2) {
            $results['properties'] = Property::query()
                ->where('landlord_id', $this->currentUserId())
                ->where('title', 'like', $like)
                ->orderByRaw('CASE WHEN LOWER(title) = ? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END', [
                    $normalized,
                    $like,
                ])
                ->latest('updated_at')
                ->take(6)
                ->get();

            if (Schema::hasTable('inspection_requests') || Schema::hasTable('occupancies')) {
                $tenantQuery = User::role('tenant')
                    ->where(function ($tenantQuery) use ($like) {
                        $tenantQuery
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    })
                    ->where(function ($scopedQuery) {
                        $scopedQuery
                            ->when(Schema::hasTable('inspection_requests'), function ($query) {
                                $query->whereHas('inspectionRequests', fn ($inspectionQuery) => $inspectionQuery->forLandlord($this->currentUserId()));
                            })
                            ->when(Schema::hasTable('occupancies'), function ($query) {
                                $query->orWhereHas('occupancies', fn ($occupancyQuery) => $occupancyQuery->whereHas('property', fn ($propertyQuery) => $propertyQuery->where('landlord_id', $this->currentUserId())));
                            });
                    })
                    ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(email) = ? THEN 1 ELSE 2 END', [
                        $normalized,
                        $normalized,
                    ])
                    ->latest('created_at')
                    ->take(6);

                $results['tenants'] = $tenantQuery->get();
            }

            if (Schema::hasTable('payment_transactions')) {
                $results['payments'] = PaymentTransaction::query()
                    ->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))
                    ->where('reference', 'like', $like)
                    ->where('status', 'paid')
                    ->whereIn('transaction_type', $this->landlordVisibleTransactionTypes())
                    ->orderByRaw('CASE WHEN reference = ? THEN 0 ELSE 1 END', [$query])
                    ->latest('created_at')
                    ->take(6)
                    ->get();
            }
        }

        return view('livewire.landlord.search', [
            'query' => $query,
            'results' => $results,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Quick Search'));
    }

    protected function landlordVisibleTransactionTypes(): array
    {
        return [
            'rent_payment',
            'purchase_payment',
            'house_purchase_payment',
            'land_purchase_payment',
        ];
    }
}
