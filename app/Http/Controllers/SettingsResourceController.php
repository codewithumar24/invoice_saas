<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Branch;
use App\Models\CustomField;
use App\Models\EmailTemplate;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Tag;
use App\Models\TaxRate;
use App\Models\Webhook;
use App\Services\AuditService;
use App\Services\Gateways\GatewayManager;
use App\Services\SettingsService;
use App\Services\WebhookService;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** The list-style settings: tax rates, exchange rates, payment methods, custom fields, tags, branches, email templates, webhooks and API tokens. */
class SettingsResourceController extends Controller
{
    /* ------------------------------ Tax ------------------------------ */

    public function tax()
    {
        return view('settings.tax', ['rates' => TaxRate::orderBy('name')->get(), 'tenant' => tenant()]);
    }

    public function updateTax(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'tax_system' => ['required', Rule::in(['exclusive', 'inclusive', 'none'])],
            'tax_label' => ['required', 'string', 'max:30'],
            'tax_number' => ['nullable', 'string', 'max:100'],
        ]);

        $settings->set(['tax_system' => $data['tax_system'], 'tax_label' => $data['tax_label'], 'tax_exempt' => $request->boolean('tax_exempt')]);
        tenant()->update(['tax_number' => $data['tax_number'] ?? null]);

        return back()->with('success', __('Tax settings saved.'));
    }

    public function storeTaxRate(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50'], 'rate' => ['required', 'numeric', 'min:0', 'max:100']]);

        if ($request->boolean('is_default')) {
            TaxRate::query()->update(['is_default' => false]);
        }

        TaxRate::create($data + ['is_default' => $request->boolean('is_default')]);

        return back()->with('success', __('Tax rate added.'));
    }

    /** Rates used on invoices are switched off rather than deleted; existing invoices keep their own copy of the rate. */
    public function destroyTaxRate(TaxRate $rate): RedirectResponse
    {
        $rate->update(['is_active' => ! $rate->is_active, 'is_default' => false]);

        return back()->with('success', $rate->is_active ? __('Tax rate switched on.') : __('Tax rate switched off.'));
    }

    /* ------------------------------ Exchange rates ------------------------------ */

    public function storeExchangeRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', Rule::in(array_keys(config('currencies'))), Rule::notIn([tenant()->currency])],
            'rate' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'effective_date' => ['required', 'date'],
        ]);

        ExchangeRate::create($data);

        return back()->with('success', __('Exchange rate saved. It applies to new documents only; existing invoices keep their rate.'));
    }

    public function destroyExchangeRate(ExchangeRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('success', __('Exchange rate deleted.'));
    }

    /* ------------------------------ Payment methods ------------------------------ */

    public function payments(GatewayManager $gateways)
    {
        return view('settings.payments', ['methods' => PaymentMethod::orderBy('name')->get(), 'types' => $gateways->types(), 'tenant' => tenant()]);
    }

    public function storePaymentMethod(Request $request, GatewayManager $gateways): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys($gateways->types()))],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'config' => ['nullable', 'array'],
            'config.*' => ['nullable', 'string', 'max:500'],
        ]);

        // Gateway keys are stored encrypted and are never shown again after saving
        PaymentMethod::create(['name' => $data['name'], 'type' => $data['type'], 'instructions' => $data['instructions'] ?? null,
            'config' => array_filter($data['config'] ?? []) ?: null]);
        app(AuditService::class)->log('payment_method.created', $data['name'], ['type' => $data['type']]);

        return back()->with('success', __('Payment method added.'));
    }

    public function togglePaymentMethod(PaymentMethod $method): RedirectResponse
    {
        $method->update(['is_active' => ! $method->is_active]);

        return back()->with('success', $method->is_active ? __('Payment method switched on.') : __('Payment method switched off.'));
    }

    public function destroyPaymentMethod(PaymentMethod $method): RedirectResponse
    {
        // Past payments keep their record; their method reference is simply emptied
        $method->delete();
        app(AuditService::class)->log('payment_method.disconnected', $method->name);

        return back()->with('success', __('Payment method removed.'));
    }

    /* ------------------------------ Custom fields ------------------------------ */

    public function customFields()
    {
        return view('settings.custom-fields', ['fields' => CustomField::orderBy('entity')->orderBy('sort_order')->get()->groupBy('entity')]);
    }

    public function storeCustomField(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity' => ['required', Rule::in(['customer', 'invoice', 'product'])],
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['text', 'number', 'date', 'select'])],
            'options' => ['nullable', 'required_if:type,select', 'string', 'max:1000'],
        ]);

        $key = Str::slug($data['label'], '_');

        if (CustomField::where('entity', $data['entity'])->where('key', $key)->exists()) {
            return back()->withErrors(['label' => __('A field with this name already exists.')]);
        }

        CustomField::create([
            'entity' => $data['entity'], 'label' => $data['label'], 'key' => $key, 'type' => $data['type'],
            'options' => $data['type'] === 'select' ? array_values(array_filter(array_map('trim', explode(',', (string) $data['options'])))) : null,
            'is_required' => $request->boolean('is_required'), 'show_on_pdf' => $request->boolean('show_on_pdf'),
            'sort_order' => CustomField::where('entity', $data['entity'])->count(),
        ]);

        return back()->with('success', __('Custom field added.'));
    }

    public function destroyCustomField(CustomField $field): RedirectResponse
    {
        $field->delete();

        return back()->with('success', __('Custom field deleted.'));
    }

    /* ------------------------------ Tags ------------------------------ */

    public function tags()
    {
        return view('settings.tags', ['tags' => Tag::orderBy('name')->get()]);
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('tags', 'name')->where('tenant_id', tenant()->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        Tag::create($data);

        return back()->with('success', __('Tag added.'));
    }

    public function destroyTag(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return back()->with('success', __('Tag deleted.'));
    }

    /* ------------------------------ Branches ------------------------------ */

    public function branches()
    {
        return view('settings.branches', ['branches' => Branch::orderBy('name')->get()]);
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['head_office', 'branch', 'warehouse'])],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        Branch::create($data + ['is_default' => ! Branch::exists()]);

        return back()->with('success', __('Branch added.'));
    }

    public function destroyBranch(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return back()->with('success', __('Branch deleted.'));
    }

    /* ------------------------------ Email templates ------------------------------ */

    public function emailTemplates()
    {
        return view('settings.email-templates', [
            'templates' => config('email_templates'),
            'customised' => EmailTemplate::where('tenant_id', tenant()->id)->pluck('key')->all(),
        ]);
    }

    public function editEmailTemplate(string $key)
    {
        abort_unless($this->customerFacing($key), 404);
        $override = EmailTemplate::where('tenant_id', tenant()->id)->where('key', $key)->first();
        $default = config("email_templates.{$key}");

        return view('settings.email-template-form', [
            'key' => $key, 'name' => $default['name'],
            'subject' => $override->subject ?? $default['subject'], 'body' => $override->body ?? $default['body'],
            'isCustom' => (bool) $override,
            'variables' => ['customer_name', 'invoice_number', 'invoice_total', 'balance_due', 'due_date', 'payment_link', 'business_name', 'quote_number', 'quote_total', 'payment_amount', 'action_url'],
        ]);
    }

    public function updateEmailTemplate(Request $request, string $key): RedirectResponse
    {
        abort_unless($this->customerFacing($key), 404);

        if ($request->boolean('reset')) {
            EmailTemplate::where('tenant_id', tenant()->id)->where('key', $key)->delete();

            return redirect()->route('settings.email-templates')->with('success', __('Template reset to the default.'));
        }

        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:10000']]);
        EmailTemplate::updateOrCreate(['tenant_id' => tenant()->id, 'key' => $key], $data);

        return redirect()->route('settings.email-templates')->with('success', __('Template saved.'));
    }

    /** Workspaces customise the emails their customers receive; account emails belong to the platform. */
    private function customerFacing(string $key): bool
    {
        return in_array($key, ['invoice_sent', 'payment_received', 'payment_failed', 'reminder_before_due', 'reminder_due', 'invoice_overdue', 'quote_sent', 'portal_invitation', 'team_invitation'], true);
    }

    /* ------------------------------ Webhooks ------------------------------ */

    public function webhooks()
    {
        return view('settings.webhooks', ['webhooks' => Webhook::withCount('deliveries')->latest()->get(), 'events' => WebhookService::EVENTS]);
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(WebhookService::EVENTS)],
        ]);

        if (! SafeUrl::isPublic($data['url'])) {
            return back()->withErrors(['url' => __('Enter a public http(s) URL.')])->withInput();
        }

        $secret = 'whsec_'.Str::random(40);
        Webhook::create(['url' => $data['url'], 'events' => array_values($data['events']), 'secret' => $secret]);

        // The signing secret is shown once, right after creation
        return back()->with('success', __('Webhook added.'))->with('webhook_secret', $secret);
    }

    public function showWebhook(Webhook $webhook)
    {
        return view('settings.webhook-deliveries', ['webhook' => $webhook, 'deliveries' => $webhook->deliveries()->paginate(25)]);
    }

    public function toggleWebhook(Webhook $webhook): RedirectResponse
    {
        $webhook->update(['is_active' => ! $webhook->is_active]);

        return back()->with('success', $webhook->is_active ? __('Webhook switched on.') : __('Webhook switched off.'));
    }

    public function destroyWebhook(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();

        return redirect()->route('settings.webhooks')->with('success', __('Webhook deleted.'));
    }

    /* ------------------------------ API tokens ------------------------------ */

    public const API_SCOPES = ['customers:read', 'customers:write', 'invoices:read', 'invoices:write', 'payments:read', 'payments:write', 'products:read', 'products:write', 'expenses:read', 'expenses:write'];

    public function apiTokens()
    {
        return view('settings.api-tokens', ['tokens' => ApiToken::with('user')->latest()->get(), 'scopes' => self::API_SCOPES]);
    }

    public function storeApiToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => [Rule::in(self::API_SCOPES)],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $plain = 'sk_'.Str::random(48);

        ApiToken::create([
            'user_id' => $request->user()->id, 'name' => $data['name'], 'token_hash' => hash('sha256', $plain),
            'abilities' => array_values($data['abilities']),
            'expires_at' => isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null,
        ]);
        app(AuditService::class)->log('api_token.created', $data['name'], ['abilities' => $data['abilities']]);

        // Only the hash is stored, so this is the single time the token can be shown
        return back()->with('success', __('API token created. Copy it now; it will not be shown again.'))->with('api_token', $plain);
    }

    public function destroyApiToken(ApiToken $token): RedirectResponse
    {
        $token->delete();
        app(AuditService::class)->log('api_token.revoked', $token->name);

        return back()->with('success', __('API token revoked.'));
    }
}
