@extends('layouts.app')
@section('title', __('Export and deletion'))

@php $isOwner = auth()->user()->isOwnerOf($tenant->id); @endphp

@section('content')
    @component('settings._layout')
        <x-card :title="__('Export your data')">
            <p class="text-sm text-muted">{{ __('Your data is yours. Download customers, invoices, payments, expenses and products as CSV or Excel at any time.') }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                @can('reports.export')
                    @foreach (['customers', 'invoices', 'payments', 'expenses', 'products'] as $type)
                        <a href="{{ route('exports.download-now', ['type' => $type, 'format' => 'xlsx']) }}" class="btn btn-secondary btn-sm"><x-icon name="download" size="14" />{{ __(ucfirst($type)) }}</a>
                    @endforeach
                @else
                    <p class="text-sm text-muted">{{ __('Ask a member with the export permission.') }}</p>
                @endcan
            </div>
        </x-card>

        <x-card :title="__('Data retention')">
            <ul class="list-disc space-y-1 ps-5 text-sm text-muted">
                <li>{{ __('Invoices and payments are never destroyed while the workspace exists; they are cancelled, voided or refunded instead.') }}</li>
                <li>{{ __('Deleted customers, products and expenses are kept in an archived state so reports and the audit log stay correct.') }}</li>
                <li>{{ __('Prepared export files are removed after 7 days. Backups follow the platform retention period.') }}</li>
            </ul>
        </x-card>

        <x-card :title="__('Delete this workspace')">
            @if ($tenant->deletion_requested_at)
                <x-alert tone="danger">
                    {{ __('Deletion was requested on :date. The workspace and its data will be deleted on :end.', ['date' => tdate($tenant->deletion_requested_at), 'end' => tdate($tenant->deletion_requested_at->copy()->addDays(30))]) }}
                </x-alert>
                @if ($isOwner)
                    <div class="mt-4"><x-action :action="route('settings.data.deletion.cancel')" method="DELETE" class="btn btn-primary">{{ __('Cancel the deletion') }}</x-action></div>
                @endif
            @elseif ($isOwner)
                <p class="text-sm text-muted">{{ __('Deleting a workspace removes its customers, invoices, payments, files and team access after a 30-day grace period. Export your data first. Some financial records may have to be kept longer where the law requires it.') }}</p>
                <form method="POST" action="{{ route('settings.data.deletion') }}" class="mt-4 max-w-md space-y-4" data-confirm="{{ __('Request deletion of :name? You can cancel during the 30-day grace period.', ['name' => $tenant->name]) }}" data-confirm-label="{{ __('Request deletion') }}">
                    @csrf
                    <x-input name="confirm_name" :label="__('Type the workspace name to confirm: :name', ['name' => $tenant->name])" required autocomplete="off" />
                    <x-input name="current_password" type="password" :label="__('Your password')" required autocomplete="current-password" />
                    <button type="submit" class="btn btn-danger">{{ __('Request deletion') }}</button>
                </form>
            @else
                <p class="text-sm text-muted">{{ __('Only the workspace owner can request deletion.') }}</p>
            @endif
        </x-card>
    @endcomponent
@endsection
