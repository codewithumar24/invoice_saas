<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\ImportService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class ProcessImportJob extends TenantJob
{
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(int $tenantId, public int $importId)
    {
        parent::__construct($tenantId);
        $this->onQueue('imports');
    }

    public function process(ImportService $imports, NotificationService $notifications): void
    {
        $import = Import::with('user')->find($this->importId);

        if (! $import || $import->status !== 'uploaded') {
            return;
        }

        // Records are created on behalf of the user who started the import
        Auth::setUser($import->user);
        $imports->process($import);

        $notifications->user($import->user, 'system', __('Import finished'),
            __(':imported rows imported, :failed failed.', ['imported' => $import->imported_rows, 'failed' => $import->failed_rows]), route('imports.show', $import));
    }
}
