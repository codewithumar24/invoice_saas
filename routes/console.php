<?php

use App\Jobs\CleanupTemporaryFilesJob;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduler. One cron entry runs everything:
|   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
| onOneServer() + withoutOverlapping() use cache locks, so tasks run once even with several app servers.
*/

// Every minute: critical system checks and queue monitoring
Schedule::command('saas:monitor')->everyMinute()->withoutOverlapping();

// Hourly: recurring invoices, reminders, cleanup
Schedule::command('saas:run hourly')->hourly()->onOneServer()->withoutOverlapping();
Schedule::job(new CleanupTemporaryFilesJob)->hourlyAt(40)->onOneServer();

// Daily: overdue detection, business signals, daily brief, subscriptions, backup
Schedule::command('saas:run daily')->dailyAt('01:00')->onOneServer()->withoutOverlapping();
Schedule::command('saas:run digest-daily')->dailyAt('06:00')->onOneServer();
Schedule::command('saas:subscriptions')->dailyAt('02:00')->onOneServer();
Schedule::command('saas:backup')->dailyAt('03:00')->onOneServer()->when(fn () => config('saas.backup.enabled'));
Schedule::command('queue:prune-failed --hours=336')->daily();
Schedule::command('queue:prune-batches')->daily();

// Weekly: business summary and full backup
Schedule::command('saas:run digest-weekly')->weeklyOn(1, '06:30')->onOneServer();
Schedule::command('saas:backup --full')->weeklyOn(7, '04:00')->onOneServer()->when(fn () => config('saas.backup.enabled'));

// Monthly: financial summary
Schedule::command('saas:run digest-monthly')->monthlyOn(1, '07:00')->onOneServer();

// Horizon metrics (only when laravel/horizon is installed)
if (class_exists(\Laravel\Horizon\Horizon::class)) {
    Schedule::command('horizon:snapshot')->everyFiveMinutes();
}
