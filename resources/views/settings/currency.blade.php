@extends('layouts.app')
@section('title', __('Currency settings'))

@section('content')
    @component('settings._layout', ['currencies' => $currencies, 'rates' => $rates])
        <x-card :title="__('Base currency')">
            <form method="POST" action="{{ route('settings.currency.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select name="currency" :label="__('Base currency')" :options="$currencies" :value="tenant()->currency" required :hint="__('Reports and the dashboard use this currency. It cannot be changed after invoices exist.')" />
                    <x-select name="currency_display" :label="__('Format')" :options="['symbol' => __('Symbol: :example', ['example' => \App\Support\Money::format(1250, tenant()->currency)]), 'code' => __('Code: :example', ['example' => \App\Support\Money::format(1250, tenant()->currency, true)])]" :value="setting('currency_display')" required />
                </div>
                @can('settings.update')<div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save currency settings') }}</button></div>@endcan
            </form>
        </x-card>

        <x-card :title="__('Exchange rates')" :padded="false">
            <p class="px-4 pt-4 text-sm text-muted sm:px-6">{{ __('Used when you invoice in another currency. Each invoice stores the rate of its own date, so old invoices are never recalculated with today\'s rate.') }}</p>
            @if ($rates->count())
                <table class="table table-stack mt-3">
                    <thead><tr><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Rate') }}</th><th>{{ __('From date') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($rates as $rate)
                            <tr>
                                <td data-label="{{ __('Currency') }}" class="font-medium">{{ $rate->currency }}</td>
                                <td data-label="{{ __('Rate') }}" class="num text-end">1 {{ $rate->currency }} = {{ rtrim(rtrim($rate->rate, '0'), '.') }} {{ tenant()->currency }}</td>
                                <td data-label="{{ __('From date') }}">{{ tdate($rate->effective_date) }}</td>
                                <td class="text-end">@can('settings.update')<x-action :action="route('settings.exchange-rates.destroy', $rate)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete this exchange rate? Invoices already created keep their rate.')" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>@endcan</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-4 sm:px-6">{{ $rates->links() }}</div>
            @endif
            @can('settings.update')
                <form method="POST" action="{{ route('settings.exchange-rates.store') }}" class="mt-4 flex flex-wrap items-end gap-3 border-t border-line p-4 sm:p-6">
                    @csrf
                    <div class="min-w-40 flex-1"><x-select name="currency" :label="__('Currency')" :options="collect($currencies)->except(tenant()->currency)->all()" required id="rate-currency" /></div>
                    <div class="w-40"><x-input name="rate" type="number" step="any" min="0" :label="__('1 unit = (:base)', ['base' => tenant()->currency])" required /></div>
                    <div><x-input name="effective_date" type="date" :label="__('From date')" :value="today_local()" required /></div>
                    <button type="submit" class="btn btn-secondary">{{ __('Add rate') }}</button>
                </form>
            @endcan
        </x-card>
    @endcomponent
@endsection
