<?php

namespace App\Livewire\Tenant\Occupancy;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\Occupancy;
use App\Models\OccupancyComplaint;
use App\Models\OccupancyMoveOutRequest;
use App\Models\PropertyPurchase;
use App\Models\User;
use App\Support\Currency;
use App\Support\WorkflowNotifier;
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
        $agreementsAvailable = Schema::hasTable('tenancy_agreements');
        $moveInReportsAvailable = Schema::hasTable('move_in_condition_reports');

        $occupancies = $occupanciesAvailable
            ? Occupancy::query()
                ->where('tenant_id', $this->currentUserId())
                ->with([
                    'property.coverImage',
                    'property.landlord.landlordProfile',
                    'moveOutRequests',
                    'complaints',
                    'tenancyAgreement',
                    'moveInConditionReport',
                ])
                ->withCount(['maintenanceRequests as maintenance_open_count' => fn ($query) => $query->where('status', '!=', 'closed')])
                ->latest('started_at')
                ->get()
            : new Collection();

        $occupancies = $occupancies
            ->sortBy(fn (Occupancy $occupancy) => match ($occupancy->status) {
                'active', 'move_out_pending' => 0,
                'upcoming' => 1,
                default => 2,
            })
            ->values();

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
            'agreementsAvailable' => $agreementsAvailable,
            'moveInReportsAvailable' => $moveInReportsAvailable,
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

        $moveOutRequest = OccupancyMoveOutRequest::create([
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

        $this->notifyMoveOutRequest($occupancy, $moveOutRequest);

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

        $complaint = OccupancyComplaint::create([
            'occupancy_id' => $occupancy->getKey(),
            'tenant_id' => $this->currentUserId(),
            'category' => trim($this->complaintCategory[$occupancyId] ?? ''),
            'description' => trim($this->complaintDescription[$occupancyId] ?? ''),
            'status' => 'open',
        ]);

        $this->complaintCategory[$occupancyId] = '';
        $this->complaintDescription[$occupancyId] = '';

        $this->notifyComplaint($occupancy, $complaint);

        session()->flash('status', 'Complaint logged. The admin team will review it soon.');
    }

    protected function notifyMoveOutRequest(Occupancy $occupancy, OccupancyMoveOutRequest $moveOutRequest): void
    {
        if (! Schema::hasTable('user_notifications')) {
            return;
        }

        $notifier = app(WorkflowNotifier::class);
        $tenant = $this->currentUser();
        $eventKey = 'move-out-request:'.$moveOutRequest->getKey();
        $notifier->notify($tenant, $eventKey, 'Move-out request submitted', $occupancy->property ? "Move-out request submitted for {$occupancy->property->title}." : 'Move-out request submitted.', route('tenant.occupancy.index'), 'move_out_request', 'View My Stay');

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($notifier, $occupancy, $eventKey): void {
            $notifier->notify($admin, $eventKey, 'Move-out request submitted', $occupancy->property ? "Tenant move-out request for {$occupancy->property->title}." : 'Tenant move-out request submitted.', route('admin.occupancy.index'), 'move_out_request', 'Review move-out');
        });
    }

    protected function notifyComplaint(Occupancy $occupancy, OccupancyComplaint $complaint): void
    {
        if (! Schema::hasTable('user_notifications')) {
            return;
        }

        $notifier = app(WorkflowNotifier::class);
        $eventKey = 'occupancy-complaint:'.$complaint->getKey();
        $notifier->notify($this->currentUser(), $eventKey, 'Complaint submitted', $occupancy->property ? "Complaint logged for {$occupancy->property->title}." : 'Complaint logged.', route('tenant.occupancy.index'), 'complaint', 'View My Stay');

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($notifier, $occupancy, $eventKey): void {
            $notifier->notify($admin, $eventKey, 'New complaint logged', $occupancy->property ? "Complaint logged for {$occupancy->property->title}." : 'Complaint logged by tenant.', route('admin.occupancy.index'), 'complaint', 'Review complaint');
        });
    }
}
