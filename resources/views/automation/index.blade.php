@extends('layouts.app')
@section('title', __('Automation'))

@section('content')
    <x-page-header :title="__('Automation')" :subtitle="__('Rules that act for you: when something happens, if it matches, then do this.')">
        <a href="{{ route('automation.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('New rule') }}</a>
    </x-page-header>

    @if ($rules->count())
        <x-table>
            <thead><tr><th>{{ __('Rule') }}</th><th>{{ __('When') }}</th><th class="text-end">{{ __('Runs') }}</th><th>{{ __('Last run') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($rules as $rule)
                    <tr>
                        <td data-label="{{ __('Rule') }}"><a href="{{ route('automation.edit', $rule) }}" class="font-medium hover:text-brand">{{ $rule->name }}</a></td>
                        <td data-label="{{ __('When') }}">{{ __($triggers[$rule->trigger] ?? $rule->trigger) }}</td>
                        <td data-label="{{ __('Runs') }}" class="num text-end">{{ $rule->run_count }}</td>
                        <td data-label="{{ __('Last run') }}" class="text-muted">{{ $rule->last_run_at?->diffForHumans() ?? __('Never') }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :tone="$rule->is_active ? 'ok' : 'neutral'">{{ $rule->is_active ? __('On') : __('Off') }}</x-badge></td>
                        <td class="text-end">
                            <div class="flex flex-wrap justify-end gap-1">
                                <x-action :action="route('automation.toggle', $rule)" class="btn btn-ghost btn-sm">{{ $rule->is_active ? __('Switch off') : __('Switch on') }}</x-action>
                                <x-action :action="route('automation.destroy', $rule)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete the rule :name?', ['name' => $rule->name])" :confirm-label="__('Delete rule')">{{ __('Delete') }}</x-action>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $rules->links() }}
    @else
        <div class="card">
            <x-empty :title="__('No automation rules yet')" :text="__('For example: when an invoice becomes overdue, if the amount is over 1,000, email the customer a reminder and notify the account manager.')">
                <a href="{{ route('automation.create') }}" class="btn btn-primary">{{ __('Create your first rule') }}</a>
            </x-empty>
        </div>
    @endif
@endsection
