<?php

namespace App\Console\Commands;

use App\Jobs\SendBusinessDigestJob;
use App\Jobs\TenantRoutineJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class RunTenantRoutines extends Command
{
    protected $signature = 'saas:run {routine : hourly, daily, digest-daily, digest-weekly or digest-monthly}';

    protected $description = 'Queue scheduled work (recurring invoices, reminders, overdue detection, signals, digests) for every active workspace';

    public function handle(): int
    {
        $routine = $this->argument('routine');
        $count = 0;

        Tenant::where('status', 'active')->select('id')->chunkById(200, function ($tenants) use ($routine, &$count) {
            foreach ($tenants as $tenant) {
                str_starts_with($routine, 'digest-')
                    ? SendBusinessDigestJob::dispatch($tenant->id, substr($routine, 7))
                    : TenantRoutineJob::dispatch($tenant->id, $routine);
                $count++;
            }
        });

        $this->info("Queued [{$routine}] for {$count} workspaces.");

        return self::SUCCESS;
    }
}
