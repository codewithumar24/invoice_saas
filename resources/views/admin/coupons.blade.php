@extends('layouts.admin')
@section('title', __('Coupons'))

@php $types = ['percent' => __('Percentage off every period'), 'fixed' => __('Fixed amount off every period'), 'first_month' => __('Percentage off the first period'), 'trial_extension' => __('Extra trial days')]; @endphp

@section('content')
    <x-page-header :title="__('Coupons')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('New coupon')">
            <form method="POST" action="{{ route('admin.coupons.store') }}" class="space-y-4">
                @csrf
                <x-input name="code" :label="__('Code')" required :hint="__('Letters, numbers, dash and underscore.')" />
                <x-select name="type" :label="__('Type')" :options="$types" required />
                <x-input name="value" type="number" step="any" min="0" :label="__('Value')" required :hint="__('Percent, amount or number of days, depending on the type.')" />
                <x-select name="plan_id" :label="__('Only for plan')" :options="$plans->pluck('name', 'id')->all()" :placeholder="__('Any plan')" />
                <x-input name="max_uses" type="number" min="1" :label="__('Usage limit')" :hint="__('Empty means unlimited.')" />
                <x-input name="expires_at" type="date" :label="__('Expires on')" />
                <button type="submit" class="btn btn-primary w-full">{{ __('Create coupon') }}</button>
            </form>
        </x-card>

        <div class="lg:col-span-2">
            <x-table>
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Discount') }}</th><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Used') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($coupons as $coupon)
                        <tr>
                            <td data-label="{{ __('Code') }}" class="num font-medium">{{ $coupon->code }}</td>
                            <td data-label="{{ __('Discount') }}">{{ (float) $coupon->value }}{{ in_array($coupon->type, ['percent', 'first_month']) ? '%' : '' }} <span class="caption block">{{ $types[$coupon->type] ?? $coupon->type }}</span></td>
                            <td data-label="{{ __('Plan') }}">{{ $coupon->plan?->name ?? __('Any') }}</td>
                            <td data-label="{{ __('Used') }}" class="num text-end">{{ $coupon->used_count }}{{ $coupon->max_uses ? ' / '.$coupon->max_uses : '' }}</td>
                            <td data-label="{{ __('Expires') }}">{{ $coupon->expires_at?->format('d M Y') ?? __('Never') }}</td>
                            <td data-label="{{ __('Status') }}"><x-badge :tone="$coupon->isRedeemable() ? 'ok' : 'neutral'">{{ $coupon->isRedeemable() ? __('Valid') : __('Not valid') }}</x-badge></td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    <x-action :action="route('admin.coupons.toggle', $coupon)" class="btn btn-ghost btn-sm">{{ $coupon->is_active ? __('Switch off') : __('Switch on') }}</x-action>
                                    <x-action :action="route('admin.coupons.destroy', $coupon)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete the coupon :code?', ['code' => $coupon->code])" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">{{ __('No coupons yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </x-table>
            {{ $coupons->links() }}
        </div>
    </div>
@endsection
