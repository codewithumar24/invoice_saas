@extends('layouts.app')
@section('title', __('Payment methods'))

@section('content')
    @component('settings._layout', ['methods' => $methods, 'types' => $types, 'tenant' => $tenant])
        <x-card :title="__('Payment methods')" :padded="false">
            <table class="table table-stack">
                <thead><tr><th>{{ __('Method') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($methods as $method)
                        <tr>
                            <td data-label="{{ __('Method') }}">
                                <span class="font-medium">{{ $method->name }}</span>
                                @if ($method->isOnline())
                                    <span class="caption block break-all">{{ __('Webhook URL') }}: {{ route('webhooks.payments', [$method->type, $tenant->slug]) }}</span>
                                @elseif ($method->instructions)
                                    <span class="caption block">{{ \Illuminate\Support\Str::limit($method->instructions, 80) }}</span>
                                @endif
                            </td>
                            <td data-label="{{ __('Type') }}">{{ __($types[$method->type] ?? $method->type) }}</td>
                            <td data-label="{{ __('Status') }}"><x-badge :tone="$method->is_active ? 'ok' : 'neutral'">{{ $method->is_active ? __('On') : __('Off') }}</x-badge></td>
                            <td class="text-end">
                                @can('settings.update')
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <x-action :action="route('settings.payment-methods.toggle', $method)" class="btn btn-ghost btn-sm">{{ $method->is_active ? __('Switch off') : __('Switch on') }}</x-action>
                                        <x-action :action="route('settings.payment-methods.destroy', $method)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="$method->isOnline() ? __('Disconnect :name? Customers can no longer pay online with it. Past payments are kept.', ['name' => $method->name]) : __('Remove :name? Past payments are kept.', ['name' => $method->name])" :confirm-label="__('Remove')">{{ __('Remove') }}</x-action>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

        @can('settings.update')
            <x-card :title="__('Add a payment method')">
                <form method="POST" action="{{ route('settings.payment-methods.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-select name="type" :label="__('Type')" :options="collect($types)->map(fn ($label) => __($label))->all()" required />
                        <x-input name="name" :label="__('Name shown to customers')" required />
                    </div>
                    <div data-show-when="type=cash|bank_transfer|manual">
                        <x-textarea name="instructions" :label="__('Instructions for the customer')" rows="3" :hint="__('For example your bank details. Shown on the invoice page.')" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2" data-show-when="type=stripe">
                        <x-input name="config[secret_key]" type="password" :label="__('Stripe secret key')" autocomplete="off" id="stripe-secret" />
                        <x-input name="config[webhook_secret]" type="password" :label="__('Stripe webhook signing secret')" autocomplete="off" id="stripe-webhook" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2" data-show-when="type=paypal">
                        <x-input name="config[client_id]" :label="__('PayPal client ID')" autocomplete="off" id="paypal-id" />
                        <x-input name="config[client_secret]" type="password" :label="__('PayPal client secret')" autocomplete="off" id="paypal-secret" />
                        <x-input name="config[webhook_id]" :label="__('PayPal webhook ID')" autocomplete="off" id="paypal-webhook" />
                        <x-select name="config[mode]" :label="__('Mode')" :options="['sandbox' => __('Sandbox (testing)'), 'live' => __('Live')]" id="paypal-mode" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2" data-show-when="type=custom">
                        <div class="sm:col-span-2"><x-input name="config[checkout_url]" type="url" :label="__('Checkout URL')" placeholder="https://gateway.example/pay?ref={token}&amount={amount}&currency={currency}" :hint="__('{token}, {amount}, {currency} and {number} are filled in for each invoice.')" id="custom-url" /></div>
                        <x-input name="config[webhook_secret]" type="password" :label="__('Webhook signing secret')" autocomplete="off" id="custom-secret" />
                    </div>

                    <div data-show-when="type=stripe|paypal|custom">
                        <x-alert tone="info">
                            {{ __('After saving, add this webhook URL in your gateway account:') }}
                            <code class="mt-1 block break-all text-xs">{{ url('webhooks/payments') }}/<span>{{ __('type') }}</span>/{{ $tenant->slug }}</code>
                            <span class="mt-1 block">{{ __('Keys are stored encrypted and are never shown again. Invoices are marked paid only after the gateway confirms the payment to this webhook.') }}</span>
                        </x-alert>
                    </div>
                    <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Add payment method') }}</button></div>
                </form>
            </x-card>
        @endcan
    @endcomponent
@endsection
