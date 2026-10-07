@extends('layouts.app')
@section('title', __('API tokens'))

@section('content')
    @component('settings._layout')
        @if (session('api_token'))
            <x-alert tone="warn">
                <p class="font-medium">{{ __('Copy your new token now. It is shown only once.') }}</p>
                <code class="mt-1 block break-all text-xs">{{ session('api_token') }}</code>
                <button type="button" class="btn btn-secondary btn-sm mt-2" data-copy="{{ session('api_token') }}" data-copied="{{ __('Copied') }}">{{ __('Copy token') }}</button>
            </x-alert>
        @endif

        <x-card :title="__('API tokens')" :padded="false">
            <p class="px-4 pt-4 text-sm text-muted sm:px-6">{{ __('Use a token to connect your own software. Send it as "Authorization: Bearer TOKEN" to :url. A token can only do what its scopes and its owner\'s role allow.', ['url' => url('api/v1')]) }}</p>
            @if ($tokens->count())
                <table class="table table-stack mt-3">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Scopes') }}</th><th>{{ __('Created by') }}</th><th>{{ __('Last used') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($tokens as $token)
                            <tr>
                                <td data-label="{{ __('Name') }}" class="font-medium">{{ $token->name }}</td>
                                <td data-label="{{ __('Scopes') }}" class="text-muted">{{ implode(', ', $token->abilities ?? []) }}</td>
                                <td data-label="{{ __('Created by') }}">{{ $token->user?->name }}</td>
                                <td data-label="{{ __('Last used') }}" class="text-muted">{{ $token->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                <td data-label="{{ __('Expires') }}">{{ $token->expires_at ? tdate($token->expires_at) : __('Never') }}</td>
                                <td class="text-end"><x-action :action="route('settings.api-tokens.destroy', $token)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Revoke the token :name? Software using it stops working immediately.', ['name' => $token->name])" :confirm-label="__('Revoke token')">{{ __('Revoke') }}</x-action></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <x-empty :title="__('No API tokens yet')" />
            @endif
        </x-card>

        <x-card :title="__('Create a token')">
            <form method="POST" action="{{ route('settings.api-tokens.store') }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input name="name" :label="__('Token name')" required :hint="__('Where will it be used? For example: Online shop')" />
                    <x-input name="expires_in_days" type="number" min="1" max="3650" :label="__('Expires after (days)')" :hint="__('Leave empty for no expiry.')" />
                </div>
                <fieldset>
                    <legend class="label">{{ __('Scopes') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($scopes as $scope)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" name="abilities[]" value="{{ $scope }}" @checked(in_array($scope, old('abilities', [])))><code>{{ $scope }}</code></label>
                        @endforeach
                    </div>
                    @error('abilities')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>
                <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Create token') }}</button></div>
            </form>
        </x-card>

        <x-card :title="__('Endpoints')">
            <ul class="space-y-1 font-mono text-xs">
                @foreach (['GET /api/v1/customers', 'POST /api/v1/customers', 'GET /api/v1/customers/{id}', 'PUT /api/v1/customers/{id}', 'GET /api/v1/invoices', 'POST /api/v1/invoices', 'GET /api/v1/invoices/{id}', 'POST /api/v1/invoices/{id}/payments', 'GET /api/v1/payments', 'GET /api/v1/products', 'POST /api/v1/products', 'GET /api/v1/expenses', 'POST /api/v1/expenses'] as $endpoint)
                    <li>{{ $endpoint }}</li>
                @endforeach
            </ul>
            <p class="mt-3 text-sm text-muted">{{ __('Responses use one shape: success, message, data. Errors add an errors object. Requests are limited to :limit per minute per token.', ['limit' => config('saas.rate_limits.api')]) }}</p>
        </x-card>
    @endcomponent
@endsection
