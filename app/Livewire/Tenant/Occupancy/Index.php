<?php

namespace App\Livewire\Tenant\Occupancy;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\Occupancy;
use App\Models\OccupancyComplaint;
use App\Models\OccupancyMoveOutRequest;
use App\Models\PropertyPurchase;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    use InteractsWithAuthenticatedUser;
    use InteractsWithRoleShells;

    public array $moveOutNotes = [];
    public array $complaintCategory = [];
    public array $complaintDescription = [];

    public function render(): View
    {
        $occupanciesAvailable = Schema::hasTable('occupancies');
        $moveOutAvailable = Schema::hasTable('occupancy_move_out_requests');
        $complaintsAvailable = Schema::hasTable('occupancy_complaints');
        $purchasesAvailable = Schema::hasTable('property_purchases');

        $occupancies = $occupanciesAvailable
            ? Occupancy::query()
                ->where('tenant_id', $this->currentUserId())
                ->with([
                    'property.coverImage',
                    'property.landlord.landlordProfile',
                    'moveOutRequests',
                    'complaints',
                ])
                ->latest('started_at')
                ->get()
            : new Collection();

        $purchases = $purchasesAvailable
            ? PropertyPurchase::query()
                ->where('buyer_id', $this->currentUserId())
                ->with([
                    'property.coverImage',
                    'property.landlord',
                ])
                ->latest('purchased_at')
                ->get()
            : new Collection();

        return view('livewire.tenant.occupancy.index', [
            'occupanciesAvailable' => $occupanciesAvailable,
            'moveOutAvailable' => $moveOutAvailable,
            'complaintsAvailable' => $complaintsAvailable,
            'occupancies' => $occupancies,
            'purchasesAvailable' => $purchasesAvailable,
            'purchases' => $purchases,
        ])->layout('layouts.dashboard-shell', $this->tenantShell('My Stays'));
    }

    public function formatMoney(float|int|string|null $amount, string $currency = 'NGN'): string
    {
        return Currency::format($amount, $currency);
    }

    public function submitMoveOutRequest(int $occupancyId): void
    {
        if (! Schema::hasTable('occupancy_move_out_requests')) {
            session()->flash('status', 'Move-out requests are not available yet.');

            return;
        }

        $occupancy = Occupancy::query()
            ->where('tenant_id', $this->currentUserId())
            ->findOrFail($occupancyId);

        if ($occupancy->status === 'moved_out') {
            session()->flash('status', 'This occupancy is already closed.');

            return;
        }

        $hasPending = OccupancyMoveOutRequest::query()
            ->where('occupancy_id', $occupancy->getKey())
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            session()->flash('status', 'A move-out request is already waiting for review.');

            return;
        }

        $notes = trim($this->moveOutNotes[$occupancyId] ?? '');

        OccupancyMoveOutRequest::create([
            'occupancy_id' => $occupancy->getKey(),
            'tenant_id' => $this->currentUserId(),
            'status' => 'pending',
            'notes' => $notes !== '' ? $notes : null,
            'requested_at' => now(),
        ]);

        $occupancy->forceFill([
            'status' => 'move_out_pending',
        ])->save();

        $this->moveOutNotes[$occupancyId] = '';

        $this->notifyMoveOutRequest($occupancy);

        session()->flash('status', 'Move-out request submitted. An admin will review it next.');
    }

    public function submitComplaint(int $occupancyId): void
    {
        if (! Schema::hasTable('occupancy_complaints')) {
            session()->flash('status', 'Complaints are not available yet.');

            return;
        }

        $this->validate([
            "complaintCategory.{$occupancyId}" => ['required', 'string', 'max:80'],
            "complaintDescription.{$occupancyId}" => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            "complaintCategory.{$occupancyId}.required" => 'Select a complaint category.',
            "complaintDescription.{$occupancyId}.required" => 'Add a short description for the complaint.',
        ]);

        $occupancy = Occupancy::query()
            ->where('tenant_id', $this->currentUserId())
            ->findOrFail($occupancyId);

        if ($occupancy->status === 'moved_out') {
            session()->flash('status', 'Complaints are closed after move-out is approved.');

            return;
        }

        OccupancyComplaint::create([
            'occupancy_id' => $occupancy->getKey(),
            'tenant_id' => $this->currentUserId(),
            'category' => trim($this->complaintCategory[$occupancyId] ?? ''),
            'description' => trim($this->complaintDescription[$occupancyId] ?? ''),
            'status' => 'open',
        ]);

        $this->complaintCategory[$occupancyId] = '';
        $this->complaintDescription[$occupancyId] = '';

        $this->notifyComplaint($occupancy);

        session()->flash('status', 'Complaint logged. The admin team will review it soon.');
    }

    protected function notifyMoveOutRequest(Occupancy $occupancy): void
    {
        if (! Schema::hasTable('user_notifications')) {
            return;
        }

        UserNotification::create([
            'user_id' => $this->currentUserId(),
            'title' => 'Move-out request submitted',
            'body' => $occupancy->property ? "Move-out request submitted for {$occupancy->property->title}." : 'Move-out request submitted.',
            'category' => 'move_out_request',
            'link' => route('tenant.occupancy.index'),
        ]);

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($occupancy): void {
            UserNotification::create([
                'user_id' => $admin->getKey(),
                'title' => 'Move-out request submitted',
                'body' => $occupancy->property ? "Tenant move-out request for {$occupancy->property->title}." : 'Tenant move-out request submitted.',
                'category' => 'move_out_request',
                'link' => route('admin.occupancy.index'),
            ]);
        });
    }

    protected function notifyComplaint(Occupancy $occupancy): void
    {
        if (! Schema::hasTable('user_notifications')) {
            return;
        }

        UserNotification::create([
            'user_id' => $this->currentUserId(),
            'title' => 'Complaint submitted',
            'body' => $occupancy->property ? "Complaint logged for {$occupancy->property->title}." : 'Complaint logged.',
            'category' => 'complaint',
            'link' => route('tenant.occupancy.index'),
        ]);

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($occupancy): void {
            UserNotification::create([
                'user_id' => $admin->getKey(),
                'title' => 'New complaint logged',
                'body' => $occupancy->property ? "Complaint logged for {$occupancy->property->title}." : 'Complaint logged by tenant.',
                'category' => 'complaint',
                'link' => route('admin.occupancy.index'),
            ]);
        });
    }
}
