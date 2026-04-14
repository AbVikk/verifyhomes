<?php

use App\Http\Controllers\TenantPaymentController;
use App\Livewire\Tenant\Dashboard as TenantDashboard;
use App\Livewire\Tenant\InspectionRequests\Index as TenantInspectionRequestIndex;
use App\Livewire\Tenant\InspectionRequests\Show as TenantInspectionRequestShow;
use App\Livewire\Tenant\Notifications\Index as TenantNotificationsIndex;
use App\Livewire\Tenant\Occupancy\Index as TenantOccupancyIndex;
use App\Livewire\Tenant\Payments\Index as TenantPaymentIndex;
use App\Livewire\Tenant\Purchases\Show as TenantPurchaseShow;
use App\Livewire\Tenant\Profile as TenantProfile;
use App\Livewire\Tenant\SavedListings\Index as TenantSavedListingsIndex;
use Illuminate\Support\Facades\Route;

Route::prefix('tenant')
    ->middleware(['auth', 'verified', 'role:tenant'])
    ->name('tenant.')
    ->group(function (): void {
        Route::get('/dashboard', TenantDashboard::class)->name('dashboard');
        Route::get('/profile', TenantProfile::class)->name('profile');
        Route::get('/saved-listings', TenantSavedListingsIndex::class)->name('saved-listings.index');
        Route::get('/occupancy', TenantOccupancyIndex::class)->name('occupancy.index');
        Route::get('/purchases/{purchase}', TenantPurchaseShow::class)->name('purchases.show');
        Route::get('/payments', TenantPaymentIndex::class)->name('payments.index');
        Route::get('/notifications', TenantNotificationsIndex::class)->name('notifications.index');
        Route::get('/payments/callback', [TenantPaymentController::class, 'handlePaymentCallback'])->name('payments.callback');
        Route::post('/inspection-requests/{inspectionRequest}/payments', [TenantPaymentController::class, 'storeInspectionRequestPayment'])->name('inspection-requests.payments.store');
        Route::post('/properties/{property}/rent-payments', [TenantPaymentController::class, 'storeRentPayment'])->name('properties.rent-payments.store');
        Route::post('/properties/{property}/purchase-payments', [TenantPaymentController::class, 'storePurchasePayment'])->name('properties.purchase-payments.store');
        Route::get('/inspection-requests', TenantInspectionRequestIndex::class)->name('inspection-requests.index');
        Route::get('/inspection-requests/{inspectionRequestId}', TenantInspectionRequestShow::class)->name('inspection-requests.show');
    });
