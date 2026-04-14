<?php

use App\Http\Controllers\InspectionRequestController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TermsGateController;
use App\Models\User;
use App\Livewire\PublicProperties\Index as PublicPropertyIndex;
use App\Livewire\PublicProperties\Show as PublicPropertyShow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/properties', PublicPropertyIndex::class)->name('properties.index');
Route::get('/properties/{property:slug}', PublicPropertyShow::class)->name('properties.show');
Route::post('/properties/{property:slug}/inspection-requests', [InspectionRequestController::class, 'store'])
    ->middleware(['auth', 'verified', 'role:tenant'])
    ->name('inspection-requests.store');
Route::post('/payments/webhooks/provider', [PaymentWebhookController::class, 'handle'])
    ->name('payments.webhooks.provider');

Route::get('/dashboard', function (Request $request) {
    /** @var User|null $user */
    $user = $request->user();

    if ($user && $user->dashboardRouteName() !== 'dashboard') {
        return redirect()->route($user->dashboardRouteName());
    }

    return view('dashboard');
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/terms-gates/open', [TermsGateController::class, 'open'])->name('terms-gates.open');
    Route::post('/terms-gates/complete', [TermsGateController::class, 'complete'])->name('terms-gates.complete');
});

require __DIR__.'/admin.php';
require __DIR__.'/landlord.php';
require __DIR__.'/tenant.php';
require __DIR__.'/auth.php';
