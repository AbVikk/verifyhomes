<?php

use App\Livewire\Landlord\Dashboard as LandlordDashboard;
use App\Livewire\Landlord\TenancyAgreements\Show as LandlordTenancyAgreementShow;
use App\Livewire\Landlord\MoveInConditionReports\Edit as LandlordMoveInConditionReportEdit;
use App\Livewire\Landlord\Maintenance\Show as LandlordMaintenanceShow;
use App\Livewire\Landlord\Maintenance\Index as LandlordMaintenanceIndex;
use App\Http\Controllers\MoveInConditionPhotoController;
use App\Http\Controllers\SupportAttachmentController;
use App\Livewire\Landlord\Documents as LandlordDocuments;
use App\Livewire\Landlord\InspectionRequests\Index as LandlordInspectionRequestIndex;
use App\Livewire\Landlord\InspectionRequests\Show as LandlordInspectionRequestShow;
use App\Livewire\Landlord\Notifications\Index as LandlordNotificationsIndex;
use App\Livewire\Landlord\Occupancy\Index as LandlordOccupancyIndex;
use App\Livewire\Landlord\Tenants\Show as LandlordTenantShow;
use App\Livewire\Landlord\Payments\Index as LandlordPaymentIndex;
use App\Livewire\Landlord\Profile as LandlordProfile;
use App\Livewire\Landlord\Search as LandlordSearch;
use App\Livewire\Landlord\Properties\Create as LandlordPropertyCreate;
use App\Livewire\Landlord\Properties\Edit as LandlordPropertyEdit;
use App\Livewire\Landlord\Properties\Index as LandlordPropertyIndex;
use App\Livewire\Support\Create as SupportCreate;
use App\Livewire\Support\Index as SupportIndex;
use App\Livewire\Support\Show as SupportShow;
use Illuminate\Support\Facades\Route;

Route::prefix('landlord')
    ->middleware(['auth', 'verified', 'role:landlord'])
    ->name('landlord.')
    ->group(function (): void {
        Route::get('/dashboard', LandlordDashboard::class)->name('dashboard');
        Route::get('/search', LandlordSearch::class)->name('search');
        Route::get('/profile', LandlordProfile::class)->name('profile');
        Route::get('/documents', LandlordDocuments::class)->name('documents');
        Route::get('/occupancy', LandlordOccupancyIndex::class)->name('occupancy.index');
        Route::get('/agreements/{agreement}', LandlordTenancyAgreementShow::class)->name('agreements.show');
        Route::get('/move-in-reports/{occupancy}', LandlordMoveInConditionReportEdit::class)->name('move-in-reports.edit');
        Route::get('/maintenance', LandlordMaintenanceIndex::class)->name('maintenance.index');
        Route::get('/maintenance/{request}', LandlordMaintenanceShow::class)->name('maintenance.show');
        Route::get('/move-in-condition-photos/{photo}', [MoveInConditionPhotoController::class, 'landlord'])->name('move-in-condition-photos.show');
        Route::get('/tenants/{tenant}', LandlordTenantShow::class)->name('tenants.show');
        Route::get('/notifications', LandlordNotificationsIndex::class)->name('notifications.index');
        Route::get('/support', SupportIndex::class)->name('support.index');
        Route::get('/support/create', SupportCreate::class)->name('support.create');
        Route::get('/support/{supportRequest}/attachments/{attachment}/view', [SupportAttachmentController::class, 'view'])->name('support.attachments.view');
        Route::get('/support/{supportRequest}/attachments/{attachment}', [SupportAttachmentController::class, 'download'])->name('support.attachments.download');
        Route::get('/support/{supportRequest}', SupportShow::class)->name('support.show');
        Route::get('/inspection-requests', LandlordInspectionRequestIndex::class)->name('inspection-requests.index');
        Route::get('/inspection-requests/{inspectionRequestId}', LandlordInspectionRequestShow::class)->name('inspection-requests.show');
        Route::get('/payments', LandlordPaymentIndex::class)->name('payments.index');
        Route::get('/properties', LandlordPropertyIndex::class)->name('properties');
        Route::get('/properties/create', LandlordPropertyCreate::class)->name('properties.create');
        Route::get('/properties/{property}/edit', LandlordPropertyEdit::class)->name('properties.edit');
    });
