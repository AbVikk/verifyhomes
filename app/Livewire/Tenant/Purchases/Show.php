<?php

namespace App\Livewire\Tenant\Purchases;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\PropertyPurchase;
use App\Support\Currency;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public PropertyPurchase $purchase;

    public function mount(PropertyPurchase $purchase): void
    {
        abort_unless($purchase->buyer_id === $this->currentUserId(), 404);

        $this->purchase = $purchase->loadMissing([
            'property.coverImage',
            'property.landlord',
            'buyer',
        ]);
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    public function render(): View
    {
        return view('livewire.tenant.purchases.show', [
            'purchase' => $this->purchase,
        ])->layout('layouts.dashboard-shell', $this->tenantShell('Purchase Receipt'));
    }
}
