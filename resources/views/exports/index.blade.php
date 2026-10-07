@extends('layouts.app')
@section('title', __('Exports'))

@section('content')
    <x-page-header :title="__('Exports')" :subtitle="__('Download your data as CSV, Excel or PDF.')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('New export')">
            <form method="POST" action="{{ route('exports.store') }}" class="space-y-4" data-no-loading>
                @csrf
                <x-select name="type" :label="__('What to export')" :options="collect($types)->filter(fn ($t) => auth()->user()->hasPermission($t.'.view'))->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])->all()" required />
                <x-select name="format" :label="__('Format')" :options="['csv' => 'CSV', 'xlsx' => 'Excel (.xlsx)', 'pdf' => 'PDF']" required />
                <div class="grid grid-cols-2 gap-4" data-show-when="type=invoices|expenses">
                    <x-input name="from" type="date" :label="__('From')" />
                    <x-input name="to" type="date" :label="__('To')" />
                </div>
                <button type="submit" class="btn btn-primary w-full"><x-icon name="download" size="16" />{{ __('Export') }}</button>
                <p class="caption">{{ __('Small exports download right away. Large ones are prepared in the background and you are notified when they are ready.') }}</p>
            </form>
        </x-card>

        <div class="lg:col-span-2">
            <h2 class="h2 mb-3">{{ __('Prepared exports') }}</h2>
            @if ($exports->count())
                <x-table>
                    <thead><tr><th>{{ __('Requested') }}</th><th>{{ __('Export') }}</th><th>{{ __('Format') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($exports as $export)
                            <tr>
                                <td data-label="{{ __('Requested') }}">{{ tdate($export->created_at, 'd M Y, H:i') }}</td>
                                <td data-label="{{ __('Export') }}">{{ __(ucfirst(str_replace(['report:', '_'], ['', ' '], $export->type))) }}</td>
                                <td data-label="{{ __('Format') }}">{{ strtoupper($export->format) }}</td>
                                <td data-label="{{ __('Status') }}"><x-badge :status="$export->status" /></td>
                                <td class="text-end">@if ($export->status === 'completed' && $export->path)<a href="{{ route('exports.download', $export) }}" class="btn btn-secondary btn-sm">{{ __('Download') }}</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
                {{ $exports->links() }}
                <p class="caption mt-3">{{ __('Prepared files are deleted after 7 days.') }}</p>
            @else
                <div class="card"><x-empty :title="__('No prepared exports')" :text="__('Large exports that run in the background are listed here when they are ready.')" /></div>
            @endif
        </div>
    </div>
@endsection
