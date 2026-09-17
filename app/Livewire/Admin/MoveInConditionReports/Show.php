<?php

namespace App\Livewire\Admin\MoveInConditionReports;

use App\Livewire\Admin\Concerns\HasAdminLayout;
use App\Models\MoveInConditionReport;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    use HasAdminLayout;
    public MoveInConditionReport $report;
    public function mount(MoveInConditionReport $report): void { $this->report = $report->loadMissing(['tenant', 'landlord', 'property', 'items.photos', 'photos']); }
    public function render(): View { return $this->adminPage(view('livewire.admin.move-in-condition-reports.show'), 'Move-in Condition Report', 'Back to Occupancy', route('admin.occupancy.index')); }
}
