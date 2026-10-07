@extends('layouts.admin')
@section('title', __('Backups'))

@section('content')
    <x-page-header :title="__('Backups')" :subtitle="$enabled ? __('Daily database backup and weekly full backup. Backups older than :days days are deleted.', ['days' => $retention]) : __('Scheduled backups are off. Set BACKUP_ENABLED=true in the environment file to switch them on.')">
        <x-action :action="route('admin.backups.run')" class="btn btn-primary">{{ __('Back up the database now') }}</x-action>
    </x-page-header>

    <x-table>
        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Type') }}</th><th>{{ __('File') }}</th><th class="text-end">{{ __('Size') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @forelse ($backups as $backup)
                <tr>
                    <td data-label="{{ __('When') }}">{{ $backup->created_at->format('d M Y H:i') }}</td>
                    <td data-label="{{ __('Type') }}">{{ status_label($backup->type) }}</td>
                    <td data-label="{{ __('File') }}" class="text-muted">{{ $backup->path ? basename($backup->path) : \Illuminate\Support\Str::limit((string) $backup->message, 100) }}</td>
                    <td data-label="{{ __('Size') }}" class="num text-end">{{ $backup->size ? number_format($backup->size / 1048576, 2).' MB' : '—' }}</td>
                    <td data-label="{{ __('Status') }}"><x-badge :status="$backup->status" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">{{ __('No backups yet.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>
    {{ $backups->links() }}
    <p class="caption mt-3">{{ __('Backups are written to storage/app/backups on the server. Copy them to a different machine or object storage; a backup on the same disk does not protect against losing the server.') }}</p>
@endsection
