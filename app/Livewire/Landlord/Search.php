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
        $results = [
            'properties' => new Collection(),
            'tenants' => new Collection(),
            'payments' => new Collection(),
        ];

        if (mb_strlen($query) >= 2) {
            $results['properties'] = Property::query()
                ->where('landlord_id', $this->currentUserId())
                ->where('title', 'like', '%'.$query.'%')
                ->latest('updated_at')
                ->take(6)
                ->get();

            if (Schema::hasTable('inspection_requests')) {
                $tenantIds = InspectionRequest::query()
                    ->forLandlord($this->currentUserId())
                    ->whereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', '%'.$query.'%'))
                    ->pluck('tenant_id')
                    ->unique()
                    ->values();

                $results['tenants'] = User::query()
                    ->whereIn('id', $tenantIds)
                    ->take(6)
                    ->get();
            }

            if (Schema::hasTable('payment_transactions')) {
                $results['payments'] = PaymentTransaction::query()
                    ->whereHas('property', fn ($query) => $query->where('landlord_id', $this->currentUserId()))
                    ->where('reference', 'like', '%'.$query.'%')
                    ->where('status', 'paid')
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
}
