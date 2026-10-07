<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;

/** Server-side PDF generation. PDFs are rendered from the same data as the screen, through Blade. */
class PdfService
{
    public function __construct(private SettingsService $settings, private FileStorageService $files) {}

    public function render(string $view, array $data, string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false); // never fetch remote resources while rendering
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // has wide Unicode coverage (currency symbols)
        $options->set('tempDir', storage_path('framework/cache'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function invoice(Invoice $invoice): string
    {
        return $this->render('pdf.invoice', $this->invoiceData($invoice) + ['pdf' => true]);
    }

    /** Render the invoice PDF and keep it in the document centre (one stored copy per invoice). */
    public function storeInvoice(Invoice $invoice): \App\Models\Attachment
    {
        $existing = $invoice->attachments()->where('category', 'invoices')->get();
        $stored = $this->files->put('invoices', $invoice->number.'.pdf', $this->invoice($invoice));

        foreach ($existing as $attachment) {
            $this->files->delete($attachment->disk, $attachment->path);
            $attachment->delete();
        }

        return $invoice->attachments()->create(['category' => 'invoices', 'mime' => 'application/pdf', 'visible_to_customer' => true] + $stored);
    }

    public function quote(Quote $quote): string
    {
        $quote->loadMissing(['customer.addresses', 'items']);

        return $this->render('pdf.quote', ['quote' => $quote, 'tenant' => tenant(), 'style' => $this->style(), 'logo' => $this->files->dataUri(tenant()->logo_path)]);
    }

    /** Data shared by the invoice PDF, the print view and the public page. */
    public function invoiceData(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer.addresses', 'items', 'taxes', 'paymentMethod', 'customFieldValues']);

        return [
            'invoice' => $invoice,
            'tenant' => tenant(),
            'style' => $this->style($invoice->template),
            'logo' => $this->files->dataUri(tenant()->logo_path),
            'customFields' => $invoice->customFieldsForDisplay(true),
        ];
    }

    /** Invoice customisation chosen by the workspace. */
    public function style(?string $template = null): array
    {
        return [
            'template' => $template ?: $this->settings->get('invoice_template'),
            'color' => $this->settings->get('invoice_color'),
            'font' => $this->settings->get('invoice_font'),
            'columns' => (array) $this->settings->get('invoice_columns'),
            'show_tax' => (bool) $this->settings->get('invoice_show_tax'),
            'show_payment_link' => (bool) $this->settings->get('invoice_show_payment_link'),
            'payment_instructions' => $this->settings->get('invoice_payment_instructions'),
            'bank_details' => $this->settings->get('invoice_bank_details'),
            'tax_label' => $this->settings->get('tax_label'),
        ];
    }
}
