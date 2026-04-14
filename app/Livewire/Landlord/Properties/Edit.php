<?php

namespace App\Livewire\Landlord\Properties;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Livewire\Landlord\Properties\Concerns\InteractsWithPropertyForm;
use App\Models\Property;
use App\Support\InspectionRequestOptions;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;
    use InteractsWithPropertyForm;
    use WithFileUploads;

    public Property $property;

    public function mount(Property $property): void
    {
        abort_unless($property->landlord_id === $this->currentUserId(), 403);

        $this->property = $property->load(['images', 'documents'])->loadCount([
            'inspectionRequests as open_inspection_requests_count' => fn ($query) => $query->open(),
            'inspectionRequests as scheduled_inspection_requests_count' => fn ($query) => $query->where('status', InspectionRequestOptions::STATUS_SCHEDULED),
        ]);
        $this->fillPropertyForm($this->property);
    }

    public function save()
    {
        $this->persistProperty($this->property);

        session()->flash('status', 'Property details updated successfully.');

        return redirect()->route('landlord.properties');
    }

    public function render(): View
    {
        $property = Property::query()
            ->whereKey($this->property->getKey())
            ->with(['images', 'documents'])
            ->withCount([
                'inspectionRequests as open_inspection_requests_count' => fn ($query) => $query->open(),
                'inspectionRequests as scheduled_inspection_requests_count' => fn ($query) => $query->where('status', InspectionRequestOptions::STATUS_SCHEDULED),
            ])
            ->firstOrFail();

        return view('livewire.landlord.properties.edit', [
            'property' => $property,
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Edit Property'));
    }
}
