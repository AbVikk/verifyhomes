<?php

namespace App\Livewire\Landlord\Properties;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Livewire\Landlord\Properties\Concerns\InteractsWithPropertyForm;
use App\Models\LandlordProfile;
use App\Models\Property;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;
    use InteractsWithPropertyForm;
    use WithFileUploads;

    public function mount(): void
    {
        $this->syncListingIntentContext();
        $this->syncPropertyTypeContext();
        $this->hasAcceptedListingTerms = $this->listingTermsReady();
    }

    public function save()
    {
        $profile = $this->landlordProfile()->fresh();

        if (! $profile->canCreateProperties()) {
            session()->flash('status', $profile->propertyCreationBlockMessage());

            return;
        }

        $property = new Property([
            'landlord_id' => $this->currentUserId(),
        ]);

        $this->persistProperty($property);

        session()->flash('status', 'Property submitted successfully and is awaiting later review.');

        return redirect()->route('landlord.properties');
    }

    public function render(): View
    {
        $profile = $this->landlordProfile()->fresh();

        return view('livewire.landlord.properties.create', [
            'canCreateProperties' => $profile->canCreateProperties(),
            'propertyCreationBlockMessage' => $profile->propertyCreationBlockMessage(),
        ])->layout('layouts.dashboard-shell', $this->landlordShell('Create Property'));
    }

    protected function landlordProfile(): LandlordProfile
    {
        $user = $this->currentUser();

        return $user->landlordProfile()->firstOrCreate(
            ['user_id' => $user->getKey()],
            [
                'verification_status' => 'pending',
                'city' => 'Akure',
                'state' => 'Ondo',
            ],
        );
    }
}
