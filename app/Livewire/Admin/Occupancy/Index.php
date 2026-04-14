<?php

namespace App\Livewire\Admin\Occupancy;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\Occupancy;
use App\Models\OccupancyComplaint;
use App\Models\OccupancyMoveOutRequest;
use App\Models\Property;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    use HasAdminLayout;

    public array $decisionNotes = [];
    public array $complaintNotes = [];

    public function render(): View
    {
        $occupanciesAvailable = Schema::hasTable('occupancies');
        $moveOutAvailable = Schema::hasTable('occupancy_move_out_requests');
        $complaintsAvailable = Schema::hasTable('occupancy_complaints');

        $moveOutRequests = $moveOutAvailable
            ? OccupancyMoveOutRequest::query()
                ->with(['occupancy.property', 'tenant'])
                ->latest('requested_at')
                ->get()
            : new Collection();

        $complaints = $complaintsAvailable
            ? OccupancyComplaint::query()
                ->with(['occupancy.property', 'tenant'])
                ->latest('created_at')
                ->get()
            : new Collection();

        $overdueOccupancies = $occupanciesAvailable
            ? Occupancy::query()
                ->active()
                ->whereNotNull('next_payment_due_at')
                ->where('next_payment_due_at', '<', now())
                ->with(['property', 'tenant'])
                ->latest('next_payment_due_at')
                ->get()
            : new Collection();

        return $this->adminPage(view('livewire.admin.occupancy.index', [
            'occupanciesAvailable' => $occupanciesAvailable,
            'moveOutAvailable' => $moveOutAvailable,
            'complaintsAvailable' => $complaintsAvailable,
            'moveOutRequests' => $moveOutRequests,
            'complaints' => $complaints,
            'overdueOccupancies' => $overdueOccupancies,
            'summary' => [
                'pending_move_outs' => $moveOutAvailable
                    ? $moveOutRequests->where('status', 'pending')->count()
                    : 0,
                'open_complaints' => $complaintsAvailable
                    ? $complaints->whereIn('status', ['open', 'in_review'])->count()
                    : 0,
                'overdue_occupancies' => $occupanciesAvailable
                    ? $overdueOccupancies->count()
                    : 0,
            ],
        ]), 'Occupancy Control Center');
    }

    public function approveMoveOut(int $requestId): void
    {
        if (! Schema::hasTable('occupancy_move_out_requests')) {
            session()->flash('status', 'Move-out requests are not available yet.');

            return;
        }

        DB::transaction(function () use ($requestId): void {
            $request = OccupancyMoveOutRequest::query()->lockForUpdate()->findOrFail($requestId);

            if ($request->status !== 'pending') {
                return;
            }

            $occupancy = Occupancy::query()->lockForUpdate()->find($request->occupancy_id);

            if (! $occupancy) {
                $request->update([
                    'status' => 'rejected',
                    'decided_at' => now(),
                    'decided_by' => auth()->id(),
                    'decision_notes' => 'Occupancy record missing at approval time.',
                ]);

                return;
            }

            $property = Property::query()->lockForUpdate()->find($occupancy->property_id);
            $units = max(1, (int) ($occupancy->units ?? 1));

            if ($property) {
                $property->forceFill([
                    'occupied_units' => max(0, (int) $property->occupied_units - $units),
                ])->save();
            }

            $occupancy->forceFill([
                'status' => 'moved_out',
                'ended_at' => now(),
            ])->save();

            $request->update([
                'status' => 'approved',
                'decided_at' => now(),
                'decided_by' => auth()->id(),
                'decision_notes' => $this->decisionNotes[$requestId] ?? null,
            ]);
        });

        $this->decisionNotes[$requestId] = '';

        session()->flash('status', 'Move-out request approved. Occupancy has been released.');
    }

    public function rejectMoveOut(int $requestId): void
    {
        if (! Schema::hasTable('occupancy_move_out_requests')) {
            session()->flash('status', 'Move-out requests are not available yet.');

            return;
        }

        DB::transaction(function () use ($requestId): void {
            $request = OccupancyMoveOutRequest::query()->lockForUpdate()->findOrFail($requestId);

            if ($request->status !== 'pending') {
                return;
            }

            $request->update([
                'status' => 'rejected',
                'decided_at' => now(),
                'decided_by' => auth()->id(),
                'decision_notes' => $this->decisionNotes[$requestId] ?? null,
            ]);

            Occupancy::query()
                ->where('id', $request->occupancy_id)
                ->where('status', 'move_out_pending')
                ->update(['status' => 'active']);
        });

        $this->decisionNotes[$requestId] = '';

        session()->flash('status', 'Move-out request rejected.');
    }

    public function markComplaintInReview(int $complaintId): void
    {
        if (! Schema::hasTable('occupancy_complaints')) {
            session()->flash('status', 'Complaints are not available yet.');

            return;
        }

        $complaint = OccupancyComplaint::query()->findOrFail($complaintId);

        $complaint->update([
            'status' => 'in_review',
            'admin_notes' => $this->complaintNotes[$complaintId] ?? null,
        ]);

        session()->flash('status', 'Complaint marked as in review.');
    }

    public function resolveComplaint(int $complaintId): void
    {
        if (! Schema::hasTable('occupancy_complaints')) {
            session()->flash('status', 'Complaints are not available yet.');

            return;
        }

        $complaint = OccupancyComplaint::query()->findOrFail($complaintId);

        $complaint->update([
            'status' => 'resolved',
            'admin_notes' => $this->complaintNotes[$complaintId] ?? null,
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        session()->flash('status', 'Complaint resolved.');
    }

    public function sendPaymentReminder(int $occupancyId): void
    {
        if (! Schema::hasTable('occupancies')) {
            session()->flash('status', 'Occupancy tracking is not available yet.');

            return;
        }

        $occupancy = Occupancy::query()->with('tenant')->findOrFail($occupancyId);

        if (! $occupancy->tenant || blank($occupancy->tenant->email)) {
            session()->flash('status', 'No tenant email is available for this occupancy.');

            return;
        }

        $this->sendReminderNotification($occupancy);

        $occupancy->update([
            'last_reminder_at' => now(),
        ]);

        $this->notifyReminder($occupancy);

        session()->flash('status', 'Reminder sent to the tenant.');
    }

    protected function sendReminderNotification(Occupancy $occupancy): void
    {
        $tenant = $occupancy->tenant;

        if (! $tenant) {
            return;
        }

        $tenant->notify(new \App\Notifications\OccupancyPaymentReminder($occupancy));
    }

    protected function notifyReminder(Occupancy $occupancy): void
    {
        if (! Schema::hasTable('user_notifications')) {
            return;
        }

        if ($occupancy->tenant) {
            UserNotification::create([
                'user_id' => $occupancy->tenant->getKey(),
                'title' => 'Payment reminder sent',
                'body' => $occupancy->property ? "Reminder sent for {$occupancy->property->title}." : 'Payment reminder sent.',
                'category' => 'reminder_sent',
                'link' => route('tenant.occupancy.index'),
            ]);
        }

        User::role(['admin', 'staff'])->get()->each(function (User $admin) use ($occupancy): void {
            UserNotification::create([
                'user_id' => $admin->getKey(),
                'title' => 'Overdue reminder sent',
                'body' => $occupancy->property ? "Reminder sent for {$occupancy->property->title}." : 'Overdue reminder sent.',
                'category' => 'reminder_sent',
                'link' => route('admin.occupancy.index'),
            ]);
        });
    }
}
