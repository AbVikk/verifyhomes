<?php
namespace App\Livewire\Admin\Maintenance; use App\Livewire\Admin\Concerns\HasAdminLayout; use App\Models\MaintenanceRequest; use Illuminate\View\View; use Livewire\Component;
class Show extends Component {use HasAdminLayout; public MaintenanceRequest $request; public function mount(MaintenanceRequest $request):void{$this->request=$request->load(['property','tenant','landlord','events','photos']);} public function render():View{return $this->adminPage(view('livewire.admin.maintenance.show'),'Maintenance request','Back to Maintenance',route('admin.maintenance.index'));}}
