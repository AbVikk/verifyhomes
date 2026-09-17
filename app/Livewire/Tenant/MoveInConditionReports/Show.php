<?php

namespace App\Livewire\Tenant\MoveInConditionReports;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\MoveInConditionPhoto;
use App\Models\MoveInConditionReport;
use App\Models\User;
use App\Support\WorkflowNotifier;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use InteractsWithAuthenticatedUser, InteractsWithRoleShells, WithFileUploads;

    public MoveInConditionReport $report;
    public bool $confirmed = false;
    public ?string $tenantNotes = null;
    public mixed $photo = null;

    public function mount(MoveInConditionReport $report): void
    {
        abort_unless($report->tenant_id === $this->currentUserId(), 404);
        $this->report = $report->loadMissing(['property', 'landlord', 'items.photos', 'photos']);
    }

    public function confirm(): void
    {
        $this->validate(['confirmed' => ['accepted']]);
        abort_unless($this->report->status === 'awaiting_tenant', 422);
        $this->report->update(['status' => 'completed', 'tenant_confirmed_at' => now()]);
        app(WorkflowNotifier::class)->notify($this->report->landlord, 'move-in-report-confirmed:'.$this->report->id, 'The tenant has confirmed the move-in condition report', 'The tenant confirmed the move-in condition report for '.($this->report->property?->title ?? 'your property').'.', route('landlord.move-in-reports.edit', $this->report->occupancy_id), 'move_in_condition', 'View report');
        session()->flash('status', 'Move-in condition report confirmed.');
    }

    public function requestChanges(): void
    {
        $this->validate(['tenantNotes' => ['required', 'string', 'min:10', 'max:1000'], 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        abort_unless($this->report->status === 'awaiting_tenant', 422);
        $this->report->update(['status' => 'changes_requested', 'tenant_notes' => $this->tenantNotes]);
        if ($this->photo) {
            $path = $this->photo->store('move-in-condition-reports/'.$this->report->id.'/tenant', 'local');
            MoveInConditionPhoto::create(['move_in_condition_report_id' => $this->report->id, 'uploaded_by_id' => $this->currentUserId(), 'source' => 'tenant', 'file_path' => $path, 'original_name' => $this->photo->getClientOriginalName(), 'file_size' => $this->photo->getSize()]);
        }
        app(WorkflowNotifier::class)->notify($this->report->landlord, 'move-in-report-changes-requested:'.$this->report->id, 'The tenant requested changes to the move-in condition report', 'Review the tenant note for '.($this->report->property?->title ?? 'your property').'.', route('landlord.move-in-reports.edit', $this->report->occupancy_id), 'move_in_condition', 'Review report');
        if (Schema::hasTable('user_notifications')) User::role(['admin', 'staff'])->get()->each(fn (User $user) => app(WorkflowNotifier::class)->notify($user, 'move-in-report-changes-requested:'.$this->report->id, 'Tenant raised an issue with a move-in condition report', 'A tenant requested changes to a move-in condition report.', route('admin.move-in-reports.show', $this->report), 'move_in_condition', 'View report'));
        $this->report->refresh()->load(['property', 'landlord', 'items.photos', 'photos']);
        $this->reset('tenantNotes', 'photo');
        session()->flash('status', 'Your note has been sent to the landlord and VerifyHomes support.');
    }

    public function render(): View { return view('livewire.tenant.move-in-condition-reports.show')->layout('layouts.dashboard-shell', $this->tenantShell('Move-in Condition Report', ['pageActionLabel' => 'Back to My Stays', 'pageActionHref' => route('tenant.occupancy.index')])); }
}
