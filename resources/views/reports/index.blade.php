@extends('layouts.app')
@section('title', __('Reports'))

@section('content')
    <x-page-header :title="__('Reports')" :subtitle="__('Amounts are in :currency, your base currency.', ['currency' => tenant()->currency])">
        @can('reports.export')<a href="{{ route('exports.index') }}" class="btn btn-secondary">{{ __('Exports') }}</a>@endcan
    </x-page-header>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($groups as $group => $reports)
            <x-card :title="__($group)" :padded="false">
                <ul class="divide-y divide-line">
                    @foreach ($reports as $report)
                        <li>
                            <a href="{{ route('reports.show', $report['key']) }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-raised sm:px-6">
                                <span class="font-medium">{{ $report['title'] }}</span>
                                @if ($report['locked'])<span class="badge badge-neutral">{{ __('Upgrade') }}</span>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </div>
@endsection
