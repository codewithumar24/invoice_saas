<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\TaxRate;
use App\Services\CurrencyService;
use App\Services\CustomerService;
use App\Services\FileStorageService;
use App\Services\SettingsService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Guided setup after registration: business, currency, tax, branding, modules, first customer, first invoice. */
class OnboardingController extends Controller
{
    public const STEPS = [1 => 'Business', 2 => 'Currency', 3 => 'Tax', 4 => 'Branding', 5 => 'Modules', 6 => 'First customer', 7 => 'First invoice'];

    public function show(Request $request, CurrencyService $currencies)
    {
        $tenant = tenant();

        if ($tenant->onboarded_at) {
            return redirect()->route('dashboard');
        }

        $step = max(1, min(7, (int) $request->query('step', $tenant->onboarding_step)));

        return view('onboarding.show', [
            'tenant' => $tenant, 'step' => min($step, $tenant->onboarding_step), 'steps' => self::STEPS,
            'timezones' => timezone_identifiers_list(), 'countries' => config('countries'), 'currencies' => $currencies->options(),
        ]);
    }

    public function store(Request $request, int $step, TenantService $tenants, SettingsService $settings, FileStorageService $files): RedirectResponse
    {
        abort_unless($request->user()->isOwnerOf(tenant()->id) && isset(self::STEPS[$step]), 403);
        $tenant = tenant();

        switch ($step) {
            case 1:
                $data = $request->validate([
                    'name' => ['required', 'string', 'max:255'],
                    'business_type' => ['required', Rule::in(array_keys(config('saas.business_types')))],
                    'country' => ['required', 'size:2', Rule::in(array_keys(config('countries')))],
                    'timezone' => ['required', 'timezone'],
                ]);
                $tenant->update(collect($data)->except('business_type')->all());
                $tenants->applyBusinessType($tenant, $data['business_type']);
                break;

            case 2:
                $data = $request->validate([
                    'currency' => ['required', Rule::in(array_keys(config('currencies')))],
                    'currency_display' => ['required', Rule::in(['symbol', 'code'])],
                ]);
                $tenant->update(['currency' => $data['currency']]);
                $settings->set(['currency_display' => $data['currency_display']]);
                break;

            case 3:
                $data = $request->validate([
                    'tax_system' => ['required', Rule::in(['exclusive', 'inclusive', 'none'])],
                    'tax_name' => ['nullable', 'string', 'max:50'],
                    'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
                    'tax_number' => ['nullable', 'string', 'max:100'],
                ]);
                $tenant->update(['tax_number' => $data['tax_number'] ?? null]);
                $settings->set(['tax_system' => $data['tax_system']]);

                if ($data['tax_system'] !== 'none' && filled($data['tax_rate'] ?? null) && ! TaxRate::exists()) {
                    TaxRate::create(['name' => $data['tax_name'] ?: 'Tax', 'rate' => $data['tax_rate'], 'is_default' => true]);
                }
                break;

            case 4:
                $data = $request->validate([
                    'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
                    'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                    'invoice_template' => ['required', Rule::in(\App\Models\Invoice::TEMPLATES)],
                ]);

                if ($request->hasFile('logo')) {
                    $tenant->logo_path = $files->storePublicImage($request->file('logo'), 'branding');
                }

                $tenant->primary_color = $data['primary_color'];
                $tenant->save();
                $settings->set(['invoice_template' => $data['invoice_template'], 'invoice_color' => $data['primary_color']]);
                break;

            case 5:
                $data = $request->validate(['modules' => ['required', 'array', 'min:1'], 'modules.*' => [Rule::in(array_keys(config('saas.modules')))]]);
                // Invoicing and payments are the core and always stay on
                $tenant->update(['enabled_modules' => array_values(array_unique(array_merge(['invoicing', 'payments'], $data['modules'])))]);
                break;

            case 6:
                if (! $request->boolean('skip')) {
                    $data = app(StoreCustomerRequest::class)->validated();
                    app(CustomerService::class)->create($data);
                }
                break;

            case 7:
                $tenant->update(['onboarded_at' => now(), 'onboarding_step' => 7]);

                return $request->boolean('create_invoice')
                    ? redirect()->route('invoices.create')->with('success', __('Setup complete. Create your first invoice below.'))
                    : redirect()->route('dashboard')->with('success', __('Setup complete. Welcome to your workspace.'));
        }

        $tenant->update(['onboarding_step' => max($tenant->onboarding_step, $step + 1)]);

        return redirect()->route('onboarding.show', ['step' => $step + 1]);
    }
}
