@extends('layouts.app')
@section('title', __('Tax settings'))

@section('content')
    @component('settings._layout', ['rates' => $rates, 'tenant' => $tenant])
        <x-card :title="__('Tax system')">
            <form method="POST" action="{{ route('settings.tax.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-select name="tax_system" :label="__('How do you charge tax?')" :options="['exclusive' => __('Added on top of prices'), 'inclusive' => __('Included in prices'), 'none' => __('No tax')]" :value="setting('tax_system')" required />
                    <x-input name="tax_label" :label="__('What do you call it?')" :value="setting('tax_label')" required placeholder="VAT, GST, Tax" />
                    <x-input name="tax_number" :label="__('Tax registration number')" :value="$tenant->tax_number" />
                </div>
                <x-switch name="tax_exempt" :label="__('This business is tax exempt')" :hint="__('No tax is calculated on new invoices and quotes.')" :checked="setting('tax_exempt')" />
                <p class="text-sm text-muted">{{ __('Changing this affects new documents only. Existing invoices keep the tax they were issued with.') }}</p>
                @can('settings.update')<div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save tax settings') }}</button></div>@endcan
            </form>
        </x-card>

        <x-card :title="__('Tax rates')" :padded="false">
            @if ($rates->count())
                <table class="table table-stack">
                    <thead><tr><th>{{ __('Name') }}</th><th class="text-end">{{ __('Rate') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($rates as $rate)
                            <tr>
                                <td data-label="{{ __('Name') }}" class="font-medium">{{ $rate->name }}@if ($rate->is_default) <span class="badge badge-brand">{{ __('Default') }}</span>@endif</td>
                                <td data-label="{{ __('Rate') }}" class="num text-end">{{ (float) $rate->rate }}%</td>
                                <td data-label="{{ __('Status') }}"><x-badge :tone="$rate->is_active ? 'ok' : 'neutral'">{{ $rate->is_active ? __('In use') : __('Off') }}</x-badge></td>
                                <td class="text-end">@can('settings.update')<x-action :action="route('settings.tax-rates.destroy', $rate)" method="DELETE" class="btn btn-ghost btn-sm">{{ $rate->is_active ? __('Switch off') : __('Switch on') }}</x-action>@endcan</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <x-empty :title="__('No tax rates yet')" :text="__('Add the rates you charge. You can use several, per invoice line.')" />
            @endif
            @can('settings.update')
                <form method="POST" action="{{ route('settings.tax-rates.store') }}" class="flex flex-wrap items-end gap-3 border-t border-line p-4 sm:p-6">
                    @csrf
                    <div class="min-w-40 flex-1"><x-input name="name" :label="__('Rate name')" required placeholder="VAT 20%" /></div>
                    <div class="w-32"><x-input name="rate" type="number" step="0.01" min="0" max="100" :label="__('Rate (%)')" required /></div>
                    <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" class="check" name="is_default" value="1">{{ __('Default') }}</label>
                    <button type="submit" class="btn btn-secondary">{{ __('Add rate') }}</button>
                </form>
            @endcan
        </x-card>
    @endcomponent
@endsection
