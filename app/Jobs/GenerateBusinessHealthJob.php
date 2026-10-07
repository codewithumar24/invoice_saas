<?php

namespace App\Jobs;

use App\Services\BusinessHealthService;

class GenerateBusinessHealthJob extends TenantJob
{
    public function __construct(int $tenantId)
    {
        parent::__construct($tenantId);
        $this->onQueue('analytics');
    }

    public function process(BusinessHealthService $health): void
    {
        $health->generate();
    }
}
