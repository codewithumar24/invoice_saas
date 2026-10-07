@extends('layouts.app')
@section('title', __('Recurring invoices'))

@section('content')
    <x-page-header :title="__('Recurring invoices')" :subtitle="__('Rules that create and send invoices on a schedule.')">
        <a href="{{ route('recurring.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('New recurring invoice') }}</a>
    </x-page-header>

    @if ($rules->count())
        <x-table>
            <thead><tr><th>{{ __('Customer') }}</th><th>{{ __('Repeats') }}</th><th>{{ __('Next invoice') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Generated') }}</th><th>{{ __('Status') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
            <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td data-label="{{ __('Customer') }}"><a href="{{ route('recurring.edit', $rule) }}" class="font-medium hover:text-brand">{{ $rule->customer?->name }}</a>@if ($rule->title)<span class="caption block">{{ $rule->title }}</span>@endif</td>
                        <td data-label="{{ __('Repeats') }}">{{ $rule->frequency === 'custom' ? __('Every :days days', ['days' => $rule->interval_days]) : status_label($rule->frequency) }}</td>
                        <td data-label="{{ __('Next invoice') }}">{{ $rule->status === 'active' ? tdate($rule->next_run_date) : '—' }}</td>
                        <td data-label="{{ __('Amount') }}" class="num text-end">{{ money($rule->amount, $rule->currency) }}</td>
                        <td data-label="{{ __('Generated') }}" class="num text-end">{{ $rule->invoices_generated }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :status="$rule->status" /></td>
                        <td class="text-end">
                            <div class="flex flex-wrap justify-end gap-1">
                                @if ($rule->status !== 'ended')
                                    <x-action :action="route('recurring.toggle', $rule)" class="btn btn-ghost btn-sm">{{ $rule->status === 'active' ? __('Pause') : __('Resume') }}</x-action>
                                @endif
                                @if ($rule->status === 'active')
                                    <x-action :action="route('recurring.run', $rule)" class="btn btn-ghost btn-sm" :confirm="__('Generate the next invoice for :name now?', ['name' => $rule->customer?->name])" :confirm-label="__('Generate now')">{{ __('Run now') }}</x-action>
                                @endif
                                <x-action :action="route('recurring.destroy', $rule)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete this recurring invoice? Invoices already generated are kept.')" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $rules->links() }}
    @else
        <div class="card">
            <x-empty :title="__('No recurring invoices yet')" :text="__('Use a recurring invoice for retainers, subscriptions and rent: choose the customer, items and schedule once, and each invoice is created and sent for you.')">
                <a href="{{ route('recurring.create') }}" class="btn btn-primary">{{ __('Create a recurring invoice') }}</a>
            </x-empty>
        </div>
    @endif
@endsection
