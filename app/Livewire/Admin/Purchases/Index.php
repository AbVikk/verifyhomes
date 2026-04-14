<?php

namespace App\Livewire\Admin\Purchases;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\PropertyPurchase;
use App\Support\Currency;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HasAdminLayout;
    use WithPagination;

    public function render(): View
    {
        $purchasesAvailable = Schema::hasTable('property_purchases');
        $baseQuery = $purchasesAvailable
            ? PropertyPurchase::query()->with(['property', 'buyer', 'paymentTransaction'])
            : null;

        $purchases = $purchasesAvailable
            ? (clone $baseQuery)
                ->latest('purchased_at')
                ->paginate(12)
            : $this->emptyPaginator();

        $summary = $purchasesAvailable
            ? [
                'total' => (clone $baseQuery)->count(),
                'house' => (clone $baseQuery)->where('purchase_type', 'house')->count(),
                'land' => (clone $baseQuery)->where('purchase_type', 'land')->count(),
                'gross' => (float) ((clone $baseQuery)->sum('gross_amount') ?: 0),
            ]
            : [
                'total' => 0,
                'house' => 0,
                'land' => 0,
                'gross' => 0,
            ];

        return $this->adminPage(view('livewire.admin.purchases.index', [
            'purchasesAvailable' => $purchasesAvailable,
            'purchases' => $purchases,
            'summary' => $summary,
        ]), 'Purchases');
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    protected function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: new Collection(),
            total: 0,
            perPage: 12,
            currentPage: 1,
            options: [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );
    }
}
