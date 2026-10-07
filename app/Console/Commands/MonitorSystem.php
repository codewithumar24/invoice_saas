<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MonitorSystem extends Command
{
    protected $signature = 'saas:monitor';

    protected $description = 'Critical checks: failed queue jobs and stuck queues. Alerts platform administrators.';

    public function handle(): int
    {
        Cache::put('scheduler:last_run', now()->toIso8601String(), 3600);

        if (! Schema::hasTable('failed_jobs')) {
            return self::SUCCESS;
        }

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(10))->count();

        // Alert at most once every 30 minutes
        if ($failed > 0 && Cache::add('monitor:failed_jobs_alert', 1, 1800)) {
            Log::channel(config('logging.default'))->warning("{$failed} queue jobs failed in the last 10 minutes.");

            User::where('is_super_admin', true)->get()->each->notify(new GenericNotification(
                'system', "{$failed} queue jobs failed in the last 10 minutes", null, route('admin.queue')
            ));
        }

        return self::SUCCESS;
    }
}
