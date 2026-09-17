<?php

namespace App\Livewire\Admin\TenancyAgreements;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\TenancyAgreement;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use HasAdminLayout;

    public TenancyAgreement $agreement;

    public function mount(TenancyAgreement $agreement): void
    {
        $this->agreement = $agreement->loadMissing(['tenant', 'landlord', 'property', 'occupancy']);
    }

    public function render(): View
    {
        return $this->adminPage(view('livewire.admin.tenancy-agreements.show', [
            'agreement' => $this->agreement,
        ]), 'Tenancy Agreement', 'Back to Occupancy', route('admin.occupancy.index'));
    }
}
