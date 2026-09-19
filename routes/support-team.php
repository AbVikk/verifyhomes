<?php

use App\Http\Controllers\SupportTeamAuthController;
use App\Http\Controllers\SupportOperationsController;
use Illuminate\Support\Facades\Route;

Route::prefix('support-team')->name('support-team.')->group(function (): void {
    Route::middleware(['auth', 'role:support_staff', 'active-support-staff'])->group(function (): void {
        Route::get('/activate/password', [SupportTeamAuthController::class, 'password'])->name('password.create');
        Route::post('/activate/password', [SupportTeamAuthController::class, 'updatePassword'])->name('password.update');
        Route::get('/dashboard', [SupportTeamAuthController::class, 'dashboard'])->name('dashboard');
        Route::get('/requests', [SupportOperationsController::class, 'supportIndex'])->name('requests.index');
        Route::get('/requests/{supportRequest}', [SupportOperationsController::class, 'supportShow'])->name('requests.show');
        Route::post('/requests/{supportRequest}/claim', [SupportOperationsController::class, 'claim'])->name('requests.claim');
        Route::post('/requests/{supportRequest}/priority', [SupportOperationsController::class, 'changePriority'])->name('requests.priority');
        Route::post('/requests/{supportRequest}/status', [SupportOperationsController::class, 'changeStatus'])->name('requests.status');
        Route::post('/requests/{supportRequest}/reply', [SupportOperationsController::class, 'publicReply'])->name('requests.reply');
        Route::post('/requests/{supportRequest}/notes', [SupportOperationsController::class, 'internalNote'])->name('requests.notes');
        Route::post('/requests/{supportRequest}/escalate', [SupportOperationsController::class, 'escalate'])->name('requests.escalate');
        Route::get('/requests/{supportRequest}/attachments/{attachment}', [SupportOperationsController::class, 'attachment'])->name('requests.attachments.view');
        Route::get('/requests/{supportRequest}/attachments/{attachment}/download', fn (\App\Models\SupportRequest $supportRequest, \App\Models\SupportRequestAttachment $attachment) => app(SupportOperationsController::class)->attachment($supportRequest, $attachment, true))->name('requests.attachments.download');
    });
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [SupportTeamAuthController::class, 'login'])->name('login');
        Route::post('/login', [SupportTeamAuthController::class, 'authenticate'])->name('login.store');
        Route::get('/activate/{token}', [SupportTeamAuthController::class, 'activation'])->name('activate');
        Route::post('/activate/{token}', [SupportTeamAuthController::class, 'verifyActivation'])->name('activate.verify');
    });
});
