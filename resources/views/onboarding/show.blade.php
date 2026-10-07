@php $title = __('Set up your workspace'); $noindex = true; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ ($isRtl ?? false) ? 'rtl' : 'ltr' }}">
<head>
    @include('partials.head')
</head>
<body>
    <main class="mx-auto w-full max-w-2xl px-4 py-10">
        <p class="text-sm text-muted">{{ __('Step :step of :total', ['step' => $step, 'total' => count($steps)]) }}</p>
        <h1 class="h1 mt-1">{{ __($steps[$step]) }}</h1>

        {{-- Progress: this really is a sequence, so the steps are numbered --}}
        <ol class="mt-4 mb-8 flex gap-1.5" aria-label="{{ __('Setup progress') }}">
            @foreach ($steps as $number => $name)
                <li class="h-1.5 flex-1 rounded-full {{ $number <= $step ? 'bg-brand' : 'bg-line' }}" @if ($number === $step) aria-current="step" @endif>
                    <span class="sr-only">{{ $number }}. {{ __($name) }}</span>
                </li>
            @endforeach
        </ol>

        @include('partials.errors')

        <form method="POST" action="{{ route('onboarding.store', $step) }}" enctype="multipart/form-data" class="card card-pad space-y-5">
            @csrf

            @switch($step)
                @case(1)
                    <x-input name="name" :label="__('Business name')" :value="$tenant->name" required />
                    <fieldset>
                        <legend class="label">{{ __('What type of business do you operate?') }}</legend>
                        <p class="field-hint mb-3 mt-0">{{ __('This decides which modules and dashboard widgets you start with. You can change them later.') }}</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach (config('saas.business_types') as $key => $type)
                                <label class="flex cursor-pointer items-center gap-3 rounded-md border border-line px-3 py-2.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                                    <input type="radio" name="business_type" value="{{ $key }}" class="accent-[var(--c-brand)]" @checked(old('business_type', $tenant->business_type) === $key) required>
                                    <span class="font-medium">{{ __($type['label']) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select name="country" :label="__('Country')" :options="$countries" :value="$tenant->country" :placeholder="__('Choose')" required />
                        <x-select name="timezone" :label="__('Timezone')" :options="array_combine($timezones, $timezones)" :value="$tenant->timezone" required />
                    </div>
                    @break

                @case(2)
                    <x-select name="currency" :label="__('Primary currency')" :options="$currencies" :value="$tenant->currency" required :hint="__('Reports are shown in this currency. Individual invoices can use other currencies.')" />
                    <x-select name="currency_display" :label="__('Currency format')" :options="['symbol' => __('Symbol, for example $1,250.00'), 'code' => __('Code, for example USD 1,250.00')]" :value="setting('currency_display')" required />
                    @break

                @case(3)
                    <x-select name="tax_system" :label="__('How do you charge tax?')" :options="['exclusive' => __('Tax is added on top of my prices'), 'inclusive' => __('My prices already include tax'), 'none' => __('I do not charge tax')]" :value="setting('tax_system')" required />
                    <div class="grid gap-4 sm:grid-cols-2" data-show-when="tax_system=exclusive|inclusive">
                        <x-input name="tax_name" :label="__('Tax name')" placeholder="VAT, GST, Sales tax" />
                        <x-input name="tax_rate" type="number" step="0.01" min="0" max="100" :label="__('Default tax rate (%)')" />
                    </div>
                    <x-input name="tax_number" :label="__('Tax registration number')" :value="$tenant->tax_number" :hint="__('Shown on your invoices. Leave empty if you have none.')" />
                    @break

                @case(4)
                    <div>
                        <label for="f-logo" class="label">{{ __('Logo') }}</label>
                        <input type="file" name="logo" id="f-logo" accept="image/png,image/jpeg,image/webp" class="input">
                        <p class="field-hint">{{ __('PNG, JPG or WebP, up to 2 MB.') }}</p>
                        @error('logo')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="f-primary_color" class="label">{{ __('Primary colour') }}</label>
                        <input type="color" name="primary_color" id="f-primary_color" value="{{ old('primary_color', $tenant->primary_color) }}" class="h-10 w-20 cursor-pointer rounded-md border border-line bg-surface p-1">
                    </div>
                    <x-select name="invoice_template" :label="__('Invoice theme')" :options="collect(\App\Models\Invoice::TEMPLATES)->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])->all()" :value="setting('invoice_template')" required />
                    @break

                @case(5)
                    <fieldset>
                        <legend class="label">{{ __('Which modules do you want to use?') }}</legend>
                        <p class="field-hint mb-3 mt-0">{{ __('Start small. Anything you leave off stays hidden until you switch it on in Settings.') }}</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach (config('saas.modules') as $key => $label)
                                <label class="flex cursor-pointer items-center gap-3 rounded-md border border-line px-3 py-2.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft">
                                    <input type="checkbox" name="modules[]" value="{{ $key }}" class="check" @checked(in_array($key, old('modules', $tenant->enabled_modules ?? []))) @disabled(in_array($key, ['invoicing', 'payments']))>
                                    <span class="font-medium">{{ __($label) }}</span>
                                </label>
                            @endforeach
                        </div>
                        {{-- Invoicing and payments are the core and always stay on --}}
                        <input type="hidden" name="modules[]" value="invoicing">
                        <input type="hidden" name="modules[]" value="payments">
                    </fieldset>
                    @break

                @case(6)
                    <p class="text-sm text-muted">{{ __('Add the first customer you want to invoice. You can import the rest from a CSV file later.') }}</p>
                    <x-input name="name" :label="__('Customer name')" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-input name="email" type="email" :label="__('Email')" />
                        <x-input name="company" :label="__('Company')" />
                    </div>
                    @break

                @case(7)
                    <p>{{ __('Your workspace is ready. Would you like a guided start with your first invoice?') }}</p>
                    <x-checkbox name="create_invoice" :label="__('Yes, take me to a new invoice')" checked />
                    @break
            @endswitch

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
                @if ($step > 1)
                    <a href="{{ route('onboarding.show', ['step' => $step - 1]) }}" class="btn btn-ghost">{{ __('Back') }}</a>
                @else
                    <span></span>
                @endif
                <div class="flex gap-2">
                    @if ($step === 6)
                        <button type="submit" name="skip" value="1" class="btn btn-secondary" formnovalidate>{{ __('Skip for now') }}</button>
                    @endif
                    <button type="submit" class="btn btn-primary">{{ $step === 7 ? __('Finish setup') : __('Save and continue') }}</button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center text-sm text-muted">@csrf<button class="link">{{ __('Sign out') }}</button></form>
    </main>
    @include('partials.flash')
</body>
</html>
