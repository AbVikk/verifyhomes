<?php
namespace App\Livewire\Admin\Maintenance; use App\Livewire\Admin\Concerns\HasAdminLayout; use App\Models\MaintenanceRequest; use Illuminate\View\View; use Livewire\Component;
class Index extends Component {use HasAdminLayout; public function render():View{$requests=MaintenanceRequest::query()->with(['property','tenant','landlord'])->latest()->get();return $this->adminPage(view('livewire.admin.maintenance.index',compact('requests')),'Maintenance');}}
