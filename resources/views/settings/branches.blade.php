@extends('layouts.app')
@section('title', __('Branches'))

@section('content')
    @component('settings._layout')
        <x-card :title="__('Branches')" :padded="false">
            <p class="px-4 pt-4 text-sm text-muted sm:px-6">{{ __('Optional. Use branches when the business has several offices or shops. Invoices and expenses can be assigned to a branch, and each branch can have its own invoice numbers.') }}</p>
            @if ($branches->count())
                <table class="table table-stack mt-3">
                    <thead><tr><th>{{ __('Branch') }}</th><th>{{ __('Type') }}</th><th>{{ __('Address') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($branches as $branch)
                            <tr>
                                <td data-label="{{ __('Branch') }}" class="font-medium">{{ $branch->name }}</td>
                                <td data-label="{{ __('Type') }}">{{ status_label($branch->type) }}</td>
                                <td data-label="{{ __('Address') }}" class="text-muted">{{ $branch->address ?: '—' }}</td>
                                <td class="text-end">@can('settings.update')<x-action :action="route('settings.branches.destroy', $branch)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete the branch :name? Its invoices and expenses are kept without a branch.', ['name' => $branch->name])" :confirm-label="__('Delete branch')">{{ __('Delete') }}</x-action>@endcan</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <x-empty :title="__('No branches')" />
            @endif
            @can('settings.update')
                <form method="POST" action="{{ route('settings.branches.store') }}" class="flex flex-wrap items-end gap-3 border-t border-line p-4 sm:p-6">
                    @csrf
                    <div class="min-w-40 flex-1"><x-input name="name" :label="__('Branch name')" required /></div>
                    <div><x-select name="type" :label="__('Type')" :options="['head_office' => __('Head office'), 'branch' => __('Branch'), 'warehouse' => __('Warehouse')]" value="branch" /></div>
                    <div class="min-w-40 flex-1"><x-input name="address" :label="__('Address')" /></div>
                    <button type="submit" class="btn btn-secondary">{{ __('Add branch') }}</button>
                </form>
            @endcan
        </x-card>
    @endcomponent
@endsection
