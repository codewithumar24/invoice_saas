<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Removes temporary export files, old import uploads, expired tokens and old login attempts. */
class CleanupTemporaryFilesJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        Export::withoutGlobalScope('tenant')->where('created_at', '<', now()->subDays(7))->whereNotNull('path')->each(function ($export) {
            Storage::disk($export->disk)->delete($export->path);
            $export->forceFill(['path' => null, 'status' => 'expired'])->save();
        });

        Import::withoutGlobalScope('tenant')->where('created_at', '<', now()->subDays(30))->each(function ($import) {
            Storage::disk($import->disk)->delete($import->path);
        });

        DB::table('password_reset_tokens')->where('created_at', '<', now()->subDay())->delete();
        DB::table('login_attempts')->where('created_at', '<', now()->subDays(90))->delete();
        DB::table('invitations')->whereNull('accepted_at')->where('expires_at', '<', now()->subDays(30))->delete();
        DB::table('webhook_events')->where('created_at', '<', now()->subDays(90))->delete();
    }
}
