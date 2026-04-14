<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\User;
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
        $results = [
            'properties' => new Collection(),
            'tenants' => new Collection(),
            'landlords' => new Collection(),
            'payments' => new Collection(),
        ];

        if (mb_strlen($query) >= 2) {
            $results['properties'] = Property::query()
                ->where('title', 'like', '%'.$query.'%')
                ->latest('updated_at')
                ->take(6)
                ->get();

            $results['tenants'] = User::role('tenant')
                ->where('name', 'like', '%'.$query.'%')
                ->latest('created_at')
                ->take(6)
                ->get();

            $results['landlords'] = User::role('landlord')
                ->where('name', 'like', '%'.$query.'%')
                ->latest('created_at')
                ->take(6)
                ->get();

            if (Schema::hasTable('payment_transactions')) {
                $results['payments'] = PaymentTransaction::query()
                    ->where('reference', 'like', '%'.$query.'%')
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
