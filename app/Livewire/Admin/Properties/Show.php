<?php

namespace App\Livewire\Admin\Properties;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Models\Property;
use App\Models\PropertyStatusHistory;
use App\Support\AuditLogger;
use App\Support\PublicPropertyVisibility;
use App\Support\ReviewStatusOptions;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Show extends Component
{
    use HasAdminLayout;
    use InteractsWithAuthenticatedUser;

    public Property $property;

    public string $occupiedUnits = '0';

    public ?string $reviewNotes = null;

    public function mount(Property $property): void
    {
        $this->property = $property;
        $this->occupiedUnits = (string) $property->occupied_units;
    }

    public function publish(): void
    {
        $property = $this->property->fresh();

        if (! PublicPropertyVisibility::canBePublished($property)) {
            $this->addError('publish', 'Only approved and verified properties can be published publicly.');

            return;
        }

        if ($property->is_published) {
            session()->flash('status', 'Property is already published.');

            return;
        }

        $property->update([
            'is_published' => true,
        ]);

        AuditLogger::log(
            action: 'property_published',
            actor: $this->currentUser(),
            target: $property,
            description: 'Published property for public discovery.',
            metadata: [
                'property_id' => $property->getKey(),
            ],
        );

        $this->property = $property->fresh();
        $this->resetValidation('publish');

        session()->flash('status', 'Property published successfully.');
    }

    public function unpublish(): void
    {
        $property = $this->property->fresh();

        if (! $property->is_published) {
            session()->flash('status', 'Property is already unpublished.');

            return;
        }

        $property->update([
            'is_published' => false,
        ]);

        AuditLogger::log(
            action: 'property_unpublished',
            actor: $this->currentUser(),
            target: $property,
            description: 'Removed property from public discovery.',
            metadata: [
                'property_id' => $property->getKey(),
            ],
        );

        $this->property = $property->fresh();
        $this->resetValidation('publish');

        session()->flash('status', 'Property unpublished successfully.');
    }

    public function changeStatus(string $status): void
    {
        if (! $this->hasPropertyStatusHistoriesTable()) {
            session()->flash('status', 'Property review actions are unavailable until property history data is available in this environment.');

            return;
        }

        validator(
            ['status' => $status, 'reviewNotes' => $this->reviewNotes],
            [
                'status' => ['required', Rule::in(ReviewStatusOptions::propertyStatusValues())],
                'reviewNotes' => ['nullable', 'string', 'max:2000'],
            ],
            [
                'status.in' => 'Select a valid property review status.',
            ],
        )->validate();

        $property = $this->property->fresh();
        $fromStatus = $property->status;
        $this->resetValidation('publish');

        if ($fromStatus === $status) {
            session()->flash('status', 'Property already has that review status.');

            return;
        }

        DB::transaction(function () use ($property, $fromStatus, $status): void {
            $approved = $status === 'approved';

            $property->update([
                'status' => $status,
                'is_verified' => $approved,
                'is_published' => false,
                'verified_at' => $approved ? now() : null,
                'verified_by' => $approved ? $this->currentUserId() : null,
            ]);

            PropertyStatusHistory::create([
                'property_id' => $property->id,
                'from_status' => $fromStatus,
                'to_status' => $status,
                'changed_by' => $this->currentUserId(),
                'notes' => $this->reviewNotes,
            ]);

            AuditLogger::log(
                action: 'property_status_changed',
                actor: $this->currentUser(),
                target: $property,
                description: 'Changed property review status from '.str($fromStatus)->headline().' to '.str($status)->headline().'.',
                metadata: [
                    'from_status' => $fromStatus,
                    'to_status' => $status,
                    'notes' => $this->reviewNotes,
                ],
            );
        });

        $this->property = $this->property->fresh();
        $this->reviewNotes = null;

        session()->flash('status', 'Property review status updated successfully.');
    }

    public function updateOccupancy(): void
    {
        $property = $this->property->fresh();

        $validated = validator(
            ['occupiedUnits' => $this->occupiedUnits],
            ['occupiedUnits' => ['required', 'integer', 'min:0', 'max:'.$property->total_units]],
            ['occupiedUnits.max' => 'Occupied units cannot exceed the total units on this listing.'],
        )->validate();

        $nextOccupiedUnits = (int) $validated['occupiedUnits'];
        $currentOccupiedUnits = (int) $property->occupied_units;

        if ($nextOccupiedUnits === $currentOccupiedUnits) {
            session()->flash('status', 'Occupied units are already set to that value.');

            return;
        }

        $property->update([
            'occupied_units' => $nextOccupiedUnits,
        ]);

        AuditLogger::log(
            action: 'property_occupancy_updated',
            actor: $this->currentUser(),
            target: $property,
            description: 'Updated property occupancy manually from the admin review detail.',
            metadata: [
                'property_id' => $property->getKey(),
                'from_occupied_units' => $currentOccupiedUnits,
                'to_occupied_units' => $nextOccupiedUnits,
                'total_units' => (int) $property->total_units,
                'available_units' => max(0, (int) $property->total_units - $nextOccupiedUnits),
            ],
        );

        $this->property = $property->fresh();
        $this->occupiedUnits = (string) $this->property->occupied_units;

        session()->flash('status', 'Occupied units updated successfully.');
    }

    public function render(): View
    {
        $historyAvailable = $this->hasPropertyStatusHistoriesTable();
        $property = $this->property->fresh()->load(
            $historyAvailable
                ? ['landlord.landlordProfile', 'images', 'documents.reviewer', 'statusHistories.changedBy']
                : ['landlord.landlordProfile', 'images', 'documents.reviewer']
        );

        return $this->adminPage(view('livewire.admin.properties.show', [
            'property' => $property,
            'canBePublished' => PublicPropertyVisibility::canBePublished($property),
            'historyAvailable' => $historyAvailable,
        ]), 'Property Review', 'Back to Properties', route('admin.properties.index'));
    }

    protected function hasPropertyStatusHistoriesTable(): bool
    {
        return Schema::hasTable('property_status_histories');
    }
}
