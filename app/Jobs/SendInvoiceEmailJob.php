<?php

namespace App\Jobs;

use App\Mail\TemplateMail;
use App\Models\Invoice;
use App\Services\NotificationService;
use App\Services\EmailTemplateService;
use App\Services\PdfService;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmailJob extends TenantJob
{
    /** @param  array|null  $message  optional custom ['subject' => , 'body' => ] typed in the "preview email" dialog */
    public function __construct(int $tenantId, public int $invoiceId, public ?array $message = null)
    {
        parent::__construct($tenantId);
        $this->onQueue('emails');
    }

    public function process(PdfService $pdf, NotificationService $notifications, EmailTemplateService $templates): void
    {
        $invoice = Invoice::with('customer')->find($this->invoiceId);

        if (! $invoice || ! $invoice->customer?->email) {
            return;
        }

        $attachment = $pdf->storeInvoice($invoice);
        $files = [['disk' => $attachment->disk, 'path' => $attachment->path, 'name' => $invoice->number.'.pdf']];

        if ($this->message && filled($this->message['body'] ?? null)) {
            Mail::to($invoice->customer->email)->queue(new TemplateMail(
                $this->message['subject'] ?: __('Invoice :number', ['number' => $invoice->number]),
                '<p>'.nl2br(e($this->message['body'])).'</p>',
                $notifications->branding(), $files, $invoice->publicUrl(), __('View invoice')
            ));

            return;
        }

        $notifications->email($invoice->customer->email, 'invoice_sent', $templates->invoiceVariables($invoice), $files, $invoice->publicUrl(), __('View invoice'));
    }
}
