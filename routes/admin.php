<?php

use App\Http\Controllers\Admin\AdminPrivateDocumentController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SupportTeamController;
use App\Http\Controllers\Admin\TenantVerificationController;
use App\Http\Controllers\MaintenancePhotoController;
use App\Http\Controllers\MoveInConditionPhotoController;
use App\Http\Controllers\SupportOperationsController;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Documents\Index as AdminDocumentIndex;
use App\Livewire\Admin\InspectionRequests\Index as AdminInspectionRequestIndex;
use App\Livewire\Admin\InspectionRequests\Show as AdminInspectionRequestShow;
use App\Livewire\Admin\Landlords\Index as AdminLandlordIndex;
use App\Livewire\Admin\Landlords\Show as AdminLandlordShow;
use App\Livewire\Admin\Maintenance\Index as AdminMaintenanceIndex;
use App\Livewire\Admin\Maintenance\Show as AdminMaintenanceShow;
use App\Livewire\Admin\MoveInConditionReports\Show as AdminMoveInConditionReportShow;
use App\Livewire\Admin\Notifications\Index as AdminNotificationsIndex;
use App\Livewire\Admin\Occupancy\Index as AdminOccupancyIndex;
use App\Livewire\Admin\Payments\Index as AdminPaymentIndex;
use App\Livewire\Admin\Properties\Index as AdminPropertyIndex;
use App\Livewire\Admin\Properties\Show as AdminPropertyShow;
use App\Livewire\Admin\Purchases\Index as AdminPurchaseIndex;
use App\Livewire\Admin\Search as AdminSearch;
use App\Livewire\Admin\Settlements\Index as AdminSettlementIndex;
use App\Livewire\Admin\TenancyAgreements\Show as AdminTenancyAgreementShow;
use App\Livewire\Admin\Tenants\Index as AdminTenantIndex;
use App\Livewire\Admin\Tenants\Show as AdminTenantShow;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth', 'verified', 'role:admin,staff', 'active-operations-staff'])->name('admin.')->group(function (): void {
    Route::get('/dashboard', AdminDashboard::class)->name('dashboard');
    Route::get('/notifications', AdminNotificationsIndex::class)->name('notifications.index');
    Route::get('/search', AdminSearch::class)->name('search');
    Route::get('/documents', AdminDocumentIndex::class)->name('documents.index');
    Route::get('/landlords', AdminLandlordIndex::class)->name('landlords.index');
    Route::get('/landlords/{landlordProfile}', AdminLandlordShow::class)->name('landlords.show');
    Route::get('/landlords/{landlordProfile}/documents/{landlordDocument}', [AdminPrivateDocumentController::class, 'landlordDocument'])->name('landlords.documents.download');
    Route::get('/tenants', AdminTenantIndex::class)->name('tenants.index');
    Route::get('/tenants/{tenantProfileId}', AdminTenantShow::class)->name('tenants.show');
    Route::get('/properties', AdminPropertyIndex::class)->name('properties.index');
    Route::get('/properties/{property}', AdminPropertyShow::class)->name('properties.show');
    Route::get('/properties/{property}/documents/{propertyDocument}', [AdminPrivateDocumentController::class, 'propertyDocument'])->name('properties.documents.download');
    Route::get('/occupancy', AdminOccupancyIndex::class)->name('occupancy.index');
    Route::get('/tenancy-agreements/{agreement}', AdminTenancyAgreementShow::class)->name('tenancy-agreements.show');
    Route::get('/move-in-reports/{report}', AdminMoveInConditionReportShow::class)->name('move-in-reports.show');
    Route::get('/move-in-condition-photos/{photo}', [MoveInConditionPhotoController::class, 'admin'])->name('move-in-condition-photos.show');
    Route::get('/maintenance', AdminMaintenanceIndex::class)->name('maintenance.index');
    Route::get('/maintenance/{request}', AdminMaintenanceShow::class)->name('maintenance.show');
    Route::get('/maintenance-photos/{photo}', [MaintenancePhotoController::class, 'show'])->name('maintenance-photos.show');
    Route::get('/payments', AdminPaymentIndex::class)->name('payments.index');
    Route::get('/settlements', AdminSettlementIndex::class)->name('settlements.index');
    Route::get('/purchases', AdminPurchaseIndex::class)->name('purchases.index');
    Route::get('/inspection-requests', AdminInspectionRequestIndex::class)->name('inspection-requests.index');
    Route::get('/inspection-requests/{inspectionRequestId}', AdminInspectionRequestShow::class)->name('inspection-requests.show');
});

Route::prefix('admin')->middleware(['auth', 'verified', 'role:admin'])->name('admin.')->group(function (): void {
    Route::get('/audit', \App\Livewire\Admin\Audit\Index::class)->name('audit.index');
    Route::get('/tenants/{tenantProfile}/verification/{document}', [TenantVerificationController::class, 'download'])->name('tenants.verification.download');
    Route::get('/tenants/{tenantProfile}/verification/{document}/preview', [TenantVerificationController::class, 'preview'])->name('tenants.verification.preview');
    Route::patch('/tenants/{tenantProfile}/verification', [TenantVerificationController::class, 'update'])->name('tenants.verification.update');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::post('/staff/{staff}/suspend', [StaffController::class, 'suspend'])->name('staff.suspend');
    Route::post('/staff/{staff}/reactivate', [StaffController::class, 'reactivate'])->name('staff.reactivate');
    Route::post('/staff/{staff}/reset-access', [StaffController::class, 'resetAccess'])->name('staff.reset-access');
    Route::get('/support', [SupportOperationsController::class, 'adminIndex'])->name('support.index');
    Route::get('/support/{supportRequest}', [SupportOperationsController::class, 'adminShow'])->name('support.show');
    Route::post('/support/{supportRequest}/assignment', [SupportOperationsController::class, 'assign'])->name('support.assignment');
    Route::post('/support/{supportRequest}/priority', [SupportOperationsController::class, 'changePriority'])->name('support.priority');
    Route::post('/support/{supportRequest}/status', [SupportOperationsController::class, 'changeStatus'])->name('support.status');
    Route::post('/support/{supportRequest}/reply', [SupportOperationsController::class, 'publicReply'])->name('support.reply');
    Route::post('/support/{supportRequest}/notes', [SupportOperationsController::class, 'internalNote'])->name('support.notes');
    Route::post('/support/{supportRequest}/clear-escalation', [SupportOperationsController::class, 'clearEscalation'])->name('support.clear-escalation');
    Route::get('/support/{supportRequest}/attachments/{attachment}', [SupportOperationsController::class, 'attachment'])->name('support.attachments.view');
    Route::get('/support/{supportRequest}/attachments/{attachment}/download', fn (\App\Models\SupportRequest $supportRequest, \App\Models\SupportRequestAttachment $attachment) => app(SupportOperationsController::class)->attachment($supportRequest, $attachment, true))->name('support.attachments.download');
});

Route::prefix('admin/support-team')->middleware(['auth', 'verified', 'role:admin'])->name('admin.support-team.')->group(function (): void {
    Route::get('/', [SupportTeamController::class, 'index'])->name('index');
    Route::post('/', [SupportTeamController::class, 'store'])->name('store');
    Route::post('/{supportStaff}/suspend', [SupportTeamController::class, 'suspend'])->name('suspend');
    Route::post('/{supportStaff}/reactivate', [SupportTeamController::class, 'reactivate'])->name('reactivate');
    Route::post('/{supportStaff}/reissue', [SupportTeamController::class, 'reissue'])->name('reissue');
});
