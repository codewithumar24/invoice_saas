<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Support\TenantContext;

/** Resolves an email template (workspace override -> platform override -> built-in default) and fills its variables. */
class EmailTemplateService
{
    public function __construct(private TenantContext $context) {}

    /** @return array{subject:string, body:string} raw template with {{variables}} */
    public function template(string $key): array
    {
        $tenantId = $this->context->id();

        $override = EmailTemplate::where('key', $key)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($q) => $q->orWhere('tenant_id', $tenantId)))
            ->orderByRaw('tenant_id IS NULL') // workspace template first
            ->first();

        $default = config("email_templates.{$key}", ['subject' => $key, 'body' => '']);

        return ['subject' => $override->subject ?? $default['subject'], 'body' => $override->body ?? $default['body']];
    }

    /**
     * @return array{subject:string, html:string} ready to send; variable values are HTML-escaped
     */
    public function render(string $key, array $variables): array
    {
        $template = $this->template($key);
        $variables += ['app_name' => config('app.name'), 'business_name' => $this->context->get()?->name ?? config('app.name')];

        return [
            'subject' => $this->replace($template['subject'], $variables, false),
            'html' => $this->toHtml($this->replace(e($template['body']), $variables, true)),
        ];
    }

    private function replace(string $text, array $variables, bool $escape): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($match) use ($variables, $escape) {
            $value = (string) ($variables[$match[1]] ?? '');

            return $escape ? e($value) : $value;
        }, $text);
    }

    /** Turn plain text into simple HTML: paragraphs, line breaks and clickable links. */
    private function toHtml(string $escapedText): string
    {
        $linked = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $escapedText);

        return collect(preg_split("/\n{2,}/", trim($linked)))->map(fn ($paragraph) => '<p>'.nl2br($paragraph).'</p>')->implode("\n");
    }

    /** Variables an invoice email can use. */
    public function invoiceVariables(\App\Models\Invoice $invoice): array
    {
        return [
            'customer_name' => $invoice->customer->name,
            'invoice_number' => $invoice->number,
            'invoice_total' => money($invoice->total, $invoice->currency),
            'balance_due' => money($invoice->balance_due, $invoice->currency),
            'due_date' => $invoice->due_date->format('d M Y'),
            'payment_link' => $invoice->publicUrl(),
            'action_url' => $invoice->publicUrl(),
        ];
    }
}
