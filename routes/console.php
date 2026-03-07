<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Maintenance\MaintenanceScheduleService;
use App\Services\Rentals\RentalStatusSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('maintenance:process-schedule {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $result = app(MaintenanceScheduleService::class)->run($dryRun);

    $this->info('Maintenance schedule processed.');
    $this->line('Started: '.$result['started']);
    $this->line('Completed: '.$result['completed']);
    $this->line('Upcoming notified: '.$result['upcoming_notified']);
    $this->line('Mode: '.($dryRun ? 'dry-run' : 'live'));
})->purpose('Process maintenance schedule and create notifications');

Artisan::command('rentals:sync-statuses {--dry-run}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $result = app(RentalStatusSyncService::class)->run($dryRun);

    $this->info('Rental status sync completed.');
    $this->line('Activated reservations: '.$result['activated']);
    $this->line('Completed reservations: '.$result['completed']);
    $this->line('Cars updated: '.$result['cars_updated']);
    $this->line('Reserve window (hours): '.$result['reserve_before_hours']);
    $this->line('Checked at: '.$result['checked_at']);
    $this->line('Mode: '.($dryRun ? 'dry-run' : 'live'));
})->purpose('Sync reservation lifecycle by date/time and update related car statuses');

Schedule::command('maintenance:process-schedule')->everyFiveMinutes();
Schedule::command('rentals:sync-statuses')->everyFiveMinutes();
