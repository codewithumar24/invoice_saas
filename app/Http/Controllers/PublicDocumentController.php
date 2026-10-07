<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\Gateways\GatewayManager;
use App\Services\InvoiceService;
use App\Services\PdfService;
use App\Services\QuoteService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Public share links for invoices and quotes. Access is by an unguessable random token,
 * never by a sequential id, and the pages expose nothing internal to the business.
 */
class PublicDocumentController extends Controller
{
    public function __construct(private TenantContext $context) {}

    private function invoiceByToken(string $token): Invoice
    {
        $invoice = Invoice::withoutGlobalScope('tenant')->where('public_token', $token)->where('status', '!=', 'draft')->firstOrFail();
        $this->enter($invoice->tenant_id);

        return $invoice;
    }

    private function quoteByToken(string $token): Quote
    {
        $quote = Quote::withoutGlobalScope('tenant')->where('public_token', $token)->where('status', '!=', 'draft')->firstOrFail();
        $this->enter($quote->tenant_id);

        return $quote;
    }

    /** From here on every query is scoped to the business that owns the document. */
    private function enter(int $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        abort_unless($tenant->isActive(), 404);
        $this->context->set($tenant);
        app()->setLocale($tenant->locale ?: config('app.locale'));
        view()->share('isRtl', in_array(app()->getLocale(), config('saas.rtl_locales'), true));
    }

    public function invoice(Request $request, string $token, InvoiceService $invoices, PdfService $pdf, GatewayManager $gateways)
    {
        $invoice = $this->invoiceByToken($token);

        // Staff previewing their own invoice should not count as a customer view
        if (! $request->user()) {
            $invoices->markViewed($invoice, $request->userAgent());
        }

        return view('public.invoice', $pdf->invoiceData($invoice) + [
            'pdf' => false,
            'onlineMethods' => (float) $invoice->balance_due > 0 && $invoice->status !== 'cancelled'
                ? PaymentMethod::where('is_active', true)->get()->filter(fn ($method) => $gateways->driver($method->type)->supportsCheckout())
                : collect(),
            'offlineMethods' => PaymentMethod::where('is_active', true)->whereIn('type', ['cash', 'bank_transfer', 'manual'])->get(),
            'justPaid' => $request->boolean('paid'),
        ]);
    }

    public function invoicePdf(string $token, PdfService $pdf)
    {
        $invoice = $this->invoiceByToken($token);

        return response($pdf->invoice($invoice), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"']);
    }

    /** Start an online payment. The invoice is only marked paid later, by the gateway's verified webhook. */
    public function pay(string $token, int $method, GatewayManager $gateways): RedirectResponse
    {
        $invoice = $this->invoiceByToken($token);
        $method = PaymentMethod::where('is_active', true)->findOrFail($method);
        $gateway = $gateways->driver($method->type);

        abort_unless($gateway->supportsCheckout() && (float) $invoice->balance_due > 0 && $invoice->status !== 'cancelled', 404);

        try {
            return redirect()->away($gateway->checkoutUrl($invoice->load('customer'), $method));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('Online payment is not available right now. Please try again later or use another payment method.'));
        }
    }

    public function quote(Request $request, string $token, QuoteService $quotes, PdfService $pdf)
    {
        $quote = $this->quoteByToken($token);

        if (! $request->user()) {
            $quotes->markViewed($quote);
        }

        $quote->load(['customer.addresses', 'items']);

        return view('public.quote', ['quote' => $quote, 'tenant' => tenant(), 'style' => $pdf->style(), 'logo' => app(\App\Services\FileStorageService::class)->dataUri(tenant()->logo_path), 'pdf' => false]);
    }

    public function quoteRespond(Request $request, string $token, QuoteService $quotes): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'in:accept,reject']]);
        $quote = $this->quoteByToken($token);
        $data['response'] === 'accept' ? $quotes->accept($quote) : $quotes->reject($quote);

        return back()->with('success', $data['response'] === 'accept' ? __('Thank you. The quote is accepted.') : __('The quote was declined.'));
    }

    public function quotePdf(string $token, PdfService $pdf)
    {
        $quote = $this->quoteByToken($token);

        return response($pdf->quote($quote), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$quote->number.'.pdf"']);
    }
}
