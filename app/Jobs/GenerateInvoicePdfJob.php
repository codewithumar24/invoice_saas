<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\PdfService;

/** Renders the invoice PDF in the background and stores it in the document centre. */
class GenerateInvoicePdfJob extends TenantJob
{
    public function __construct(int $tenantId, public int $invoiceId)
    {
        parent::__construct($tenantId);
        $this->onQueue('invoices');
    }

    public function process(PdfService $pdf): void
    {
        if ($invoice = Invoice::find($this->invoiceId)) {
            $pdf->storeInvoice($invoice);
        }
    }
}
