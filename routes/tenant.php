<?php

use App\Http\Controllers\TenantPaymentController;
use App\Livewire\Tenant\Dashboard as TenantDashboard;
use App\Livewire\Tenant\TenancyAgreements\Show as TenantTenancyAgreementShow;
use App\Livewire\Tenant\MoveInConditionReports\Show as TenantMoveInConditionReportShow;
use App\Livewire\Tenant\Maintenance\Index as TenantMaintenanceIndex;
use App\Livewire\Tenant\Maintenance\Show as TenantMaintenanceShow;
use App\Http\Controllers\MaintenancePhotoController;
use App\Http\Controllers\SupportAttachmentController;
use App\Http\Controllers\MoveInConditionPhotoController;
use App\Livewire\Tenant\InspectionRequests\Index as TenantInspectionRequestIndex;
use App\Livewire\Tenant\InspectionRequests\Show as TenantInspectionRequestShow;
use App\Livewire\Tenant\Notifications\Index as TenantNotificationsIndex;
use App\Livewire\Tenant\Occupancy\Index as TenantOccupancyIndex;
use App\Livewire\Tenant\Payments\Index as TenantPaymentIndex;
use App\Livewire\Tenant\Purchases\Show as TenantPurchaseShow;
use App\Livewire\Tenant\Profile as TenantProfile;
use App\Livewire\Tenant\Verification as TenantVerification;
use App\Livewire\Tenant\SavedListings\Index as TenantSavedListingsIndex;
use App\Livewire\Support\Create as SupportCreate;
use App\Livewire\Support\Index as SupportIndex;
use App\Livewire\Support\Show as SupportShow;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant')
    ->middleware(['auth', 'verified', 'role:tenant'])
    ->name('tenant.')
    ->group(function (): void {
        Route::get('/dashboard', TenantDashboard::class)->name('dashboard');
        Route::get('/profile', TenantProfile::class)->name('profile');
        Route::get('/verification', TenantVerification::class)->name('verification');
        Route::get('/saved-listings', TenantSavedListingsIndex::class)->name('saved-listings.index');
        Route::get('/occupancy', TenantOccupancyIndex::class)->name('occupancy.index');
        Route::get('/agreements/{agreement}', TenantTenancyAgreementShow::class)->name('agreements.show');
        Route::get('/move-in-reports/{report}', TenantMoveInConditionReportShow::class)->name('move-in-reports.show');
        Route::get('/maintenance', TenantMaintenanceIndex::class)->name('maintenance.index');
        Route::get('/maintenance/{request}', TenantMaintenanceShow::class)->name('maintenance.show');
        Route::get('/maintenance-photos/{photo}', [MaintenancePhotoController::class, 'show'])->name('maintenance-photos.show');
        Route::get('/move-in-condition-photos/{photo}', [MoveInConditionPhotoController::class, 'tenant'])->name('move-in-condition-photos.show');
        Route::get('/purchases/{purchase}', TenantPurchaseShow::class)->name('purchases.show');
        Route::get('/payments', TenantPaymentIndex::class)->name('payments.index');
        Route::get('/notifications', TenantNotificationsIndex::class)->name('notifications.index');
        Route::get('/support', SupportIndex::class)->name('support.index');
        Route::get('/support/create', SupportCreate::class)->name('support.create');
        Route::get('/support/{supportRequest}/attachments/{attachment}/view', [SupportAttachmentController::class, 'view'])->name('support.attachments.view');
        Route::get('/support/{supportRequest}/attachments/{attachment}', [SupportAttachmentController::class, 'download'])->name('support.attachments.download');
        Route::get('/support/{supportRequest}', SupportShow::class)->name('support.show');
        Route::get('/payments/callback', [TenantPaymentController::class, 'handlePaymentCallback'])->name('payments.callback');
        Route::post('/inspection-requests/{inspectionRequest}/payments', [TenantPaymentController::class, 'storeInspectionRequestPayment'])->name('inspection-requests.payments.store');
        Route::post('/properties/{property}/rent-payments', [TenantPaymentController::class, 'storeRentPayment'])->name('properties.rent-payments.store');
        Route::post('/properties/{property}/purchase-payments', [TenantPaymentController::class, 'storePurchasePayment'])->name('properties.purchase-payments.store');
        Route::get('/inspection-requests', TenantInspectionRequestIndex::class)->name('inspection-requests.index');
        Route::get('/inspection-requests/{inspectionRequestId}', TenantInspectionRequestShow::class)->name('inspection-requests.show');
    });
