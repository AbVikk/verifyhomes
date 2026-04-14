<?php

use App\Http\Controllers\Admin\AdminPrivateDocumentController;
use App\Livewire\Admin\Audit\Index as AdminAuditIndex;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Notifications\Index as AdminNotificationsIndex;
use App\Livewire\Admin\Search as AdminSearch;
use App\Livewire\Admin\Documents\Index as AdminDocumentIndex;
use App\Livewire\Admin\InspectionRequests\Index as AdminInspectionRequestIndex;
use App\Livewire\Admin\InspectionRequests\Show as AdminInspectionRequestShow;
use App\Livewire\Admin\Landlords\Index as AdminLandlordIndex;
use App\Livewire\Admin\Landlords\Show as AdminLandlordShow;
use App\Livewire\Admin\Occupancy\Index as AdminOccupancyIndex;
use App\Livewire\Admin\Payments\Index as AdminPaymentIndex;
use App\Livewire\Admin\Purchases\Index as AdminPurchaseIndex;
use App\Livewire\Admin\Properties\Index as AdminPropertyIndex;
use App\Livewire\Admin\Properties\Show as AdminPropertyShow;
use App\Livewire\Admin\Tenants\Index as AdminTenantIndex;
use App\Livewire\Admin\Tenants\Show as AdminTenantShow;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth', 'verified', 'role:admin,staff'])
    ->name('admin.')
    ->group(function (): void {
        Route::get('/audit', AdminAuditIndex::class)->name('audit.index');
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
        Route::get('/payments', AdminPaymentIndex::class)->name('payments.index');
        Route::get('/purchases', AdminPurchaseIndex::class)->name('purchases.index');
        Route::get('/inspection-requests', AdminInspectionRequestIndex::class)->name('inspection-requests.index');
        Route::get('/inspection-requests/{inspectionRequestId}', AdminInspectionRequestShow::class)->name('inspection-requests.show');
    });
