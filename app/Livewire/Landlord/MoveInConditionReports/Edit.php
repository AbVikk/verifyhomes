<?php

namespace App\Livewire\Landlord\MoveInConditionReports;

use App\Livewire\Concerns\InteractsWithAuthenticatedUser;
use App\Livewire\Concerns\InteractsWithRoleShells;
use App\Models\MoveInConditionPhoto;
use App\Models\MoveInConditionReport;
use App\Models\Occupancy;
use App\Support\MoveInConditionReportService;
use App\Support\WorkflowNotifier;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use InteractsWithAuthenticatedUser, InteractsWithRoleShells, WithFileUploads;

    public MoveInConditionReport $report;
    public array $items = [];
    public mixed $photo = null;
    public ?int $photoItemId = null;

    public function mount(Occupancy $occupancy): void
    {
        abort_unless($occupancy->property?->landlord_id === $this->currentUserId(), 404);
        $occupancy->loadMissing(['property', 'tenancyAgreement', 'moveInConditionReport.items', 'moveInConditionReport.photos']);
        $this->report = app(MoveInConditionReportService::class)->createForOccupancy($occupancy);
        $this->loadItemState();
    }

    public function saveDraft(): void
    {
        $this->assertEditable();
        if (! $this->persistItems(false)) return;
        session()->flash('status', 'Move-in condition report draft saved.');
    }

    public function submitToTenant(): void
    {
        $this->assertEditable();
        if (! $this->persistItems(true)) return;
        $this->report->update(['status' => 'awaiting_tenant', 'submitted_at' => now(), 'tenant_notes' => null]);
        $this->report->refresh();
        app(WorkflowNotifier::class)->notify($this->report->tenant, 'move-in-report-ready:'.$this->report->id, 'Your move-in condition report is ready to review', 'Review the move-in condition report for '.($this->report->property?->title ?? 'your rental property').'.', route('tenant.move-in-reports.show', $this->report), 'move_in_condition', 'Review report');
        session()->flash('status', 'Report submitted to the tenant for confirmation.');
    }

    public function addPhoto(): void
    {
        $this->assertEditable();
        $this->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], 'photoItemId' => ['nullable', 'integer']]);
        abort_if($this->report->photos()->count() >= 30, 422, 'This report already has the maximum of 30 photos.');
        $itemId = $this->photoItemId;
        abort_unless($itemId === null || $this->report->items()->whereKey($itemId)->exists(), 422);
        $path = $this->photo->store('move-in-condition-reports/'.$this->report->id.'/landlord', 'local');
        MoveInConditionPhoto::create(['move_in_condition_report_id' => $this->report->id, 'move_in_condition_item_id' => $itemId, 'uploaded_by_id' => $this->currentUserId(), 'source' => 'landlord', 'file_path' => $path, 'original_name' => $this->photo->getClientOriginalName(), 'file_size' => $this->photo->getSize()]);
        $this->reset('photo', 'photoItemId');
        $this->report->load('photos');
        session()->flash('status', 'Condition photo added to the draft.');
    }

    public function render(): View
    {
        return view('livewire.landlord.move-in-condition-reports.edit', ['ratings' => MoveInConditionReportService::RATINGS])
            ->layout('layouts.dashboard-shell', $this->landlordShell('Move-in Condition Report', ['pageActionLabel' => 'Back to Occupants', 'pageActionHref' => route('landlord.occupancy.index')]));
    }

    private function loadItemState(): void
    {
        $this->items = $this->report->items->mapWithKeys(fn ($item) => [$item->id => ['rating' => $item->rating, 'notes' => $item->notes]])->all();
    }

    private function persistItems(bool $submitting): bool
    {
        $rules = [];
        foreach ($this->report->items as $item) {
            $rules["items.{$item->id}.rating"] = $submitting ? ['required', 'in:good,fair,damaged,not_applicable'] : ['nullable', 'in:good,fair,damaged,not_applicable'];
            $rules["items.{$item->id}.notes"] = ['nullable', 'string', 'max:1000'];
        }
        $this->validate($rules);
        foreach ($this->report->items as $item) {
            $value = $this->items[$item->id] ?? [];
            if (($value['rating'] ?? null) === 'damaged' && blank($value['notes'] ?? null)) $this->addError("items.{$item->id}.notes", 'Add a short note for damaged items.');
        }
        if ($this->getErrorBag()->isNotEmpty()) return false;
        foreach ($this->report->items as $item) $item->update(['rating' => $this->items[$item->id]['rating'] ?? null, 'notes' => $this->items[$item->id]['notes'] ?? null]);
        return true;
    }

    private function assertEditable(): void { abort_unless($this->report->canBeEditedByLandlord(), 422, 'This report is no longer editable.'); }
}
