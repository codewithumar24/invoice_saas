<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\AuditService;
use App\Services\CurrencyService;
use App\Services\FileStorageService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Business settings: general, branding, invoice, notifications, security, modules, data and audit log. */
class SettingsController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function business()
    {
        return view('settings.business', ['tenant' => tenant(), 'timezones' => timezone_identifiers_list(), 'countries' => config('countries')]);
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'size:2', Rule::in(array_keys(config('countries')))],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(array_keys(config('saas.locales')))],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'business_type' => ['nullable', Rule::in(array_keys(config('saas.business_types')))],
        ]);

        tenant()->update($data);
        app(AuditService::class)->log('settings.business_updated', tenant()->name);

        return back()->with('success', __('Business details saved.'));
    }

    public function branding()
    {
        return view('settings.branding', ['tenant' => tenant()]);
    }

    public function updateBranding(Request $request, FileStorageService $files): RedirectResponse
    {
        $data = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico', 'max:512'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'remove_logo' => ['nullable', 'boolean'],
            'email_from_name' => ['nullable', 'string', 'max:100'],
            'email_footer' => ['nullable', 'string', 'max:500'],
            'white_label' => ['nullable', 'boolean'],
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^(?!-)[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', Rule::unique('tenants', 'custom_domain')->ignore(tenant()->id)],
        ]);

        $tenant = tenant();

        if ($request->boolean('remove_logo')) {
            $files->delete('public', $tenant->logo_path);
            $tenant->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            $files->delete('public', $tenant->logo_path);
            $tenant->logo_path = $files->storePublicImage($request->file('logo'), 'branding');
        }

        if ($request->hasFile('favicon')) {
            $files->delete('public', $tenant->favicon_path);
            $tenant->favicon_path = $files->storePublicImage($request->file('favicon'), 'branding');
        }

        $tenant->primary_color = $data['primary_color'];

        // Custom domain and white-label are plan features
        if (feature('custom_domain')) {
            $tenant->custom_domain = $data['custom_domain'] ? strtolower($data['custom_domain']) : null;
        }

        $tenant->save();

        $this->settings->set([
            'email_from_name' => $data['email_from_name'] ?? '', 'email_footer' => $data['email_footer'] ?? '',
            'white_label' => feature('white_label') && $request->boolean('white_label'),
        ]);

        return back()->with('success', __('Branding saved.'));
    }

    public function invoices()
    {
        return view('settings.invoices', ['templates' => Invoice::TEMPLATES]);
    }

    public function updateInvoices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'quote_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'payment_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'invoice_next_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'invoice_padding' => ['required', 'integer', 'min:1', 'max:10'],
            'invoice_reset' => ['required', Rule::in(['never', 'yearly', 'monthly'])],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'invoice_terms' => ['nullable', 'string', 'max:5000'],
            'invoice_notes' => ['nullable', 'string', 'max:5000'],
            'invoice_footer' => ['nullable', 'string', 'max:255'],
            'invoice_template' => ['required', Rule::in(Invoice::TEMPLATES)],
            'invoice_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'invoice_font' => ['required', Rule::in(['DejaVu Sans', 'DejaVu Serif', 'Helvetica', 'Times', 'Courier'])],
            'invoice_columns' => ['nullable', 'array'],
            'invoice_columns.*' => [Rule::in(['description', 'quantity', 'unit', 'price', 'discount', 'tax'])],
            'invoice_payment_instructions' => ['nullable', 'string', 'max:2000'],
            'invoice_bank_details' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->settings->set(array_map(fn ($value) => $value ?? '', $data) + [
            'invoice_columns' => $data['invoice_columns'] ?? [],
            'invoice_branch_sequence' => $request->boolean('invoice_branch_sequence'),
            'invoice_show_tax' => $request->boolean('invoice_show_tax'),
            'invoice_show_qr' => $request->boolean('invoice_show_qr'),
            'invoice_show_payment_link' => $request->boolean('invoice_show_payment_link'),
            'track_invoice_views' => $request->boolean('track_invoice_views'),
        ]);

        return back()->with('success', __('Invoice settings saved.'));
    }

    public function notifications()
    {
        return view('settings.notifications');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reminder_before_days' => ['required', 'integer', 'min:0', 'max:60'],
            'reminder_after_days' => ['nullable', 'string', 'max:100', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
        ]);

        $this->settings->set([
            'reminders_enabled' => $request->boolean('reminders_enabled'),
            'reminder_before_days' => (int) $data['reminder_before_days'],
            'reminder_on_due' => $request->boolean('reminder_on_due'),
            'reminder_after_days' => array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($data['reminder_after_days'] ?? '')))))),
            'digest_daily' => $request->boolean('digest_daily'),
            'digest_weekly' => $request->boolean('digest_weekly'),
            'digest_monthly' => $request->boolean('digest_monthly'),
        ]);

        return back()->with('success', __('Notification settings saved.'));
    }

    public function security()
    {
        return view('settings.security');
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        // Do not lock yourself out: you need 2FA on your own account before requiring it from everyone
        if ($request->boolean('two_factor_required') && ! $request->user()->hasTwoFactor()) {
            return back()->with('error', __('Enable two-factor authentication on your own account first.'));
        }

        $this->settings->set(['two_factor_required' => $request->boolean('two_factor_required'), 'portal_enabled' => $request->boolean('portal_enabled')]);
        app(AuditService::class)->log('settings.security_updated', null, ['two_factor_required' => $request->boolean('two_factor_required')]);

        return back()->with('success', __('Security settings saved.'));
    }

    public function modules()
    {
        return view('settings.modules', ['tenant' => tenant()]);
    }

    public function updateModules(Request $request): RedirectResponse
    {
        $data = $request->validate(['modules' => ['nullable', 'array'], 'modules.*' => [Rule::in(array_keys(config('saas.modules')))]]);
        tenant()->update(['enabled_modules' => array_values(array_unique(array_merge(['invoicing', 'payments'], $data['modules'] ?? [])))]);

        return back()->with('success', __('Modules saved.'));
    }

    public function currency(CurrencyService $currencies)
    {
        return view('settings.currency', [
            'currencies' => $currencies->options(),
            'rates' => \App\Models\ExchangeRate::orderByDesc('effective_date')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function updateCurrency(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'currency' => ['required', Rule::in(array_keys(config('currencies')))],
            'currency_display' => ['required', Rule::in(['symbol', 'code'])],
        ]);

        // The base currency is fixed once invoices exist: old documents were converted with it
        if ($data['currency'] !== tenant()->currency && Invoice::exists()) {
            return back()->with('error', __('The base currency cannot be changed after invoices exist. Use a different currency per invoice instead.'));
        }

        tenant()->update(['currency' => $data['currency']]);
        $this->settings->set(['currency_display' => $data['currency_display']]);

        return back()->with('success', __('Currency settings saved.'));
    }

    /* ------------------------------ Data: export and deletion ------------------------------ */

    public function data()
    {
        return view('settings.data', ['tenant' => tenant()]);
    }

    /** Deletion is a request with a grace period, not an instant destroy. Financial records may need to be retained by law. */
    public function requestDeletion(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwnerOf(tenant()->id), 403);
        $request->validate(['current_password' => ['required', 'current_password'], 'confirm_name' => ['required', Rule::in([tenant()->name])]], ['confirm_name.in' => __('Type the workspace name exactly to confirm.')]);

        tenant()->update(['deletion_requested_at' => now()]);
        app(AuditService::class)->log('workspace.deletion_requested', tenant()->name);

        return back()->with('success', __('Deletion requested. The workspace will be deleted after a 30-day grace period. You can cancel until then.'));
    }

    public function cancelDeletion(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwnerOf(tenant()->id), 403);
        tenant()->update(['deletion_requested_at' => null]);
        app(AuditService::class)->log('workspace.deletion_cancelled', tenant()->name);

        return back()->with('success', __('Deletion request cancelled.'));
    }

    public function audit(Request $request)
    {
        $logs = AuditLog::with('user')->where('tenant_id', tenant()->id)
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', $this->like($action)))
            ->when($request->query('entity'), fn ($q, $entity) => $q->where('entity_type', $entity))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->orderByDesc('id')->paginate(30)->withQueryString();

        return view('settings.audit', ['logs' => $logs, 'members' => tenant()->users()->orderBy('name')->get(), 'entities' => ['Invoice', 'Payment', 'Customer', 'Expense', 'Product', 'Quote', 'Role']]);
    }
}
