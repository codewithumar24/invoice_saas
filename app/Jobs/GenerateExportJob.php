<?php

namespace App\Jobs;

use App\Models\Export;
use App\Services\ExportService;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use Throwable;

/** Large exports and reports: generate file -> store temporarily -> notify the user -> download. */
class GenerateExportJob extends TenantJob
{
    public int $timeout = 600;

    public function __construct(int $tenantId, public int $exportId)
    {
        parent::__construct($tenantId);
        $this->onQueue('exports');
    }

    public function process(ExportService $exports, FileStorageService $files, NotificationService $notifications): void
    {
        $export = Export::with('user')->find($this->exportId);

        if (! $export) {
            return;
        }

        $export->update(['status' => 'processing']);

        try {
            $contents = $exports->render($exports->dataset($export->type, $export->filters ?? []), $export->format);
            $stored = $files->put('exports', $exports->filename($export->type, $export->format), $contents);
            $export->update(['status' => 'completed', 'disk' => $stored['disk'], 'path' => $stored['path'], 'completed_at' => now()]);

            $notifications->user($export->user, 'system', __('Your export is ready'), __('Download it from the exports page.'), route('exports.index'), true);
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed']);
            throw $exception;
        }
    }
}
