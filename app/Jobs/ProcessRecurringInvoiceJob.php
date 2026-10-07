<?php

namespace App\Jobs;

use App\Models\RecurringInvoice;
use App\Services\RecurringInvoiceService;

/** Generate the next invoice of one recurring rule right now ("Run now" button). */
class ProcessRecurringInvoiceJob extends TenantJob
{
    public function __construct(int $tenantId, public int $ruleId)
    {
        parent::__construct($tenantId);
        $this->onQueue('invoices');
    }

    public function process(RecurringInvoiceService $recurring): void
    {
        $rule = RecurringInvoice::find($this->ruleId);

        if ($rule && $rule->status === 'active') {
            $recurring->generate($rule);
        }
    }
}
