<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Support\RentReminderGenerator;
use App\Support\LandlordSettlementReminderGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rent-reminders:generate', function (RentReminderGenerator $reminders) {
    Log::info('Rent reminder generation started.');

    $created = $reminders->generate();
    $message = "Generated {$created} rent reminder notification".($created === 1 ? '' : 's').'.';

    Log::info('Rent reminder generation finished.', [
        'notifications_created' => $created,
    ]);

    $this->info($message);
})->purpose('Generate rent reminder notifications for active rental occupancies');

Schedule::command('rent-reminders:generate')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Artisan::command('settlements:generate-reminders', function (LandlordSettlementReminderGenerator $reminders) {
    $this->info('Generated '.$reminders->generate().' landlord settlement reminder notification(s).');
})->purpose('Generate internal reminders for outstanding landlord settlements');

Schedule::command('settlements:generate-reminders')->dailyAt('07:00')->withoutOverlapping();
