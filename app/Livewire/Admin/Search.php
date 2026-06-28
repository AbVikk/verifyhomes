<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\TenantProfile;
use App\Models\LandlordProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Url;
use Livewire\Component;

class Search extends Component
{
    use HasAdminLayout;

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
            'landlords' => new Collection(),
            'payments' => new Collection(),
        ];

        if (mb_strlen($query) >= 2) {
            $results['properties'] = Property::query()
                ->where('title', 'like', $like)
                ->orderByRaw('CASE WHEN LOWER(title) = ? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END', [
                    $normalized,
                    $like,
                ])
                ->latest('updated_at')
                ->take(6)
                ->get();

            if (Schema::hasTable('tenant_profiles')) {
                $results['tenants'] = TenantProfile::query()
                    ->select('tenant_profiles.*')
                    ->join('users', 'users.id', '=', 'tenant_profiles.user_id')
                    ->where(function ($tenantQuery) use ($like) {
                        $tenantQuery
                            ->where('users.name', 'like', $like)
                            ->orWhere('users.email', 'like', $like);
                    })
                    ->orderByRaw('CASE WHEN LOWER(users.name) = ? THEN 0 WHEN LOWER(users.email) = ? THEN 1 ELSE 2 END', [
                        $normalized,
                        $normalized,
                    ])
                    ->latest('tenant_profiles.updated_at')
                    ->with('user')
                    ->take(6)
                    ->get();
            }

            if (Schema::hasTable('landlord_profiles')) {
                $results['landlords'] = LandlordProfile::query()
                    ->select('landlord_profiles.*')
                    ->join('users', 'users.id', '=', 'landlord_profiles.user_id')
                    ->where(function ($landlordQuery) use ($like) {
                        $landlordQuery
                            ->where('users.name', 'like', $like)
                            ->orWhere('users.email', 'like', $like)
                            ->orWhere('landlord_profiles.business_name', 'like', $like);
                    })
                    ->orderByRaw('CASE WHEN LOWER(users.name) = ? THEN 0 WHEN LOWER(users.email) = ? THEN 1 ELSE 2 END', [
                        $normalized,
                        $normalized,
                    ])
                    ->latest('landlord_profiles.updated_at')
                    ->with('user')
                    ->take(6)
                    ->get();
            }

            if (Schema::hasTable('payment_transactions')) {
                $results['payments'] = PaymentTransaction::query()
                    ->where(function ($paymentQuery) use ($like) {
                        $paymentQuery
                            ->where('reference', 'like', $like)
                            ->orWhereHas('payer', function ($payerQuery) use ($like) {
                                $payerQuery
                                    ->where('name', 'like', $like)
                                    ->orWhere('email', 'like', $like);
                            })
                            ->orWhereHas('property', function ($propertyQuery) use ($like) {
                                $propertyQuery
                                    ->where('title', 'like', $like)
                                    ->orWhereHas('landlord', function ($landlordQuery) use ($like) {
                                        $landlordQuery
                                            ->where('name', 'like', $like)
                                            ->orWhere('email', 'like', $like);
                                    });
                            });
                    })
                    ->orderByRaw('CASE WHEN reference = ? THEN 0 ELSE 1 END', [$query])
                    ->latest('created_at')
                    ->take(6)
                    ->get();
            }
        }

        return $this->adminPage(view('livewire.admin.search', [
            'query' => $query,
            'results' => $results,
        ]), 'Quick Search');
    }
}
