<?php

use App\Livewire\Landlord\Dashboard as LandlordDashboard;
use App\Livewire\Landlord\Documents as LandlordDocuments;
use App\Livewire\Landlord\InspectionRequests\Index as LandlordInspectionRequestIndex;
use App\Livewire\Landlord\InspectionRequests\Show as LandlordInspectionRequestShow;
use App\Livewire\Landlord\Notifications\Index as LandlordNotificationsIndex;
use App\Livewire\Landlord\Occupancy\Index as LandlordOccupancyIndex;
use App\Livewire\Landlord\Payments\Index as LandlordPaymentIndex;
use App\Livewire\Landlord\Profile as LandlordProfile;
use App\Livewire\Landlord\Search as LandlordSearch;
use App\Livewire\Landlord\Properties\Create as LandlordPropertyCreate;
use App\Livewire\Landlord\Properties\Edit as LandlordPropertyEdit;
use App\Livewire\Landlord\Properties\Index as LandlordPropertyIndex;
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
        Route::get('/notifications', LandlordNotificationsIndex::class)->name('notifications.index');
        Route::get('/inspection-requests', LandlordInspectionRequestIndex::class)->name('inspection-requests.index');
        Route::get('/inspection-requests/{inspectionRequestId}', LandlordInspectionRequestShow::class)->name('inspection-requests.show');
        Route::get('/payments', LandlordPaymentIndex::class)->name('payments.index');
        Route::get('/properties', LandlordPropertyIndex::class)->name('properties');
        Route::get('/properties/create', LandlordPropertyCreate::class)->name('properties.create');
        Route::get('/properties/{property}/edit', LandlordPropertyEdit::class)->name('properties.edit');
    });
