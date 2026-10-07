<?php

namespace App\Jobs;

use App\Models\Quote;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\PdfService;

class SendQuoteEmailJob extends TenantJob
{
    public function __construct(int $tenantId, public int $quoteId)
    {
        parent::__construct($tenantId);
        $this->onQueue('emails');
    }

    public function process(PdfService $pdf, NotificationService $notifications, FileStorageService $files): void
    {
        $quote = Quote::with('customer')->find($this->quoteId);

        if (! $quote || ! $quote->customer?->email) {
            return;
        }

        $stored = $files->put('quotes', $quote->number.'.pdf', $pdf->quote($quote));

        $notifications->email($quote->customer->email, 'quote_sent', [
            'customer_name' => $quote->customer->name,
            'quote_number' => $quote->number,
            'quote_total' => money($quote->total, $quote->currency),
            'action_url' => $quote->publicUrl(),
        ], [['disk' => $stored['disk'], 'path' => $stored['path'], 'name' => $quote->number.'.pdf']], $quote->publicUrl(), __('View quote'));
    }
}
