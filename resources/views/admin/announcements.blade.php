@extends('layouts.admin')
@section('title', __('Announcements'))

@section('content')
    <x-page-header :title="__('Announcements')" :subtitle="__('Shown as a banner at the top of the app to the workspaces you target.')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('New announcement')">
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-4">
                @csrf
                <x-input name="title" :label="__('Title')" required />
                <x-textarea name="body" :label="__('Message')" rows="3" required />
                <x-select name="type" :label="__('Type')" :options="['system' => __('System'), 'feature' => __('New feature'), 'maintenance' => __('Maintenance notice')]" required />
                <x-select name="target_type" :label="__('Show to')" :options="['all' => __('All users'), 'plans' => __('Specific plans'), 'countries' => __('Specific countries'), 'tenants' => __('Specific tenants')]" required />
                <div data-show-when="target_type=plans|countries|tenants">
                    <x-input name="target_values" :label="__('Targets')" :hint="__('Comma separated. Plans: plan ids (:plans). Countries: two-letter codes. Tenants: tenant ids.', ['plans' => $plans->map(fn ($p) => $p->id.' = '.$p->name)->implode(', ')])" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <x-input name="starts_at" type="date" :label="__('From')" />
                    <x-input name="ends_at" type="date" :label="__('Until')" />
                </div>
                <button type="submit" class="btn btn-primary w-full">{{ __('Publish announcement') }}</button>
            </form>
        </x-card>

        <div class="lg:col-span-2">
            <x-table>
                <thead><tr><th>{{ __('Announcement') }}</th><th>{{ __('Shown to') }}</th><th>{{ __('Period') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($announcements as $announcement)
                        <tr>
                            <td data-label="{{ __('Announcement') }}"><span class="font-medium">{{ $announcement->title }}</span><span class="caption block">{{ \Illuminate\Support\Str::limit($announcement->body, 90) }}</span></td>
                            <td data-label="{{ __('Shown to') }}">{{ status_label($announcement->target_type) }}@if ($announcement->target_values)<span class="caption block">{{ implode(', ', $announcement->target_values) }}</span>@endif</td>
                            <td data-label="{{ __('Period') }}">{{ $announcement->starts_at?->format('d M') ?? __('Now') }} – {{ $announcement->ends_at?->format('d M Y') ?? __('No end') }}</td>
                            <td class="text-end"><x-action :action="route('admin.announcements.destroy', $announcement)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Remove this announcement?')" :confirm-label="__('Remove')">{{ __('Remove') }}</x-action></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">{{ __('No announcements.') }}</td></tr>
                    @endforelse
                </tbody>
            </x-table>
            {{ $announcements->links() }}
        </div>
    </div>
@endsection
