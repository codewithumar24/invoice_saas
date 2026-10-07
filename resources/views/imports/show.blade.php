@extends('layouts.app')
@section('title', __('Import :type', ['type' => __($import->type)]))

@section('content')
    <x-page-header :title="__('Import :type', ['type' => __($import->type)])" :back="route('imports.index')">
        <x-badge :status="$import->status" class="text-sm" />
    </x-page-header>

    @if ($import->status === 'uploaded')
        <form method="POST" action="{{ route('imports.start', $import) }}" class="space-y-6">
            @csrf
            <x-card :title="__('Match your columns')">
                <p class="mb-4 text-sm text-muted">{{ __('For each field, choose the column of your file that holds it. Fields you leave empty are skipped.') }}</p>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($fields as $field => [$label, $rules])
                        <div>
                            <label for="map-{{ $field }}" class="label">{{ __($label) }}@if (str_contains($rules, 'required'))<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
                            <select name="mapping[{{ $field }}]" id="map-{{ $field }}" class="input">
                                <option value="">{{ __('Skip this field') }}</option>
                                @foreach ($preview['header'] as $index => $column)
                                    <option value="{{ $index }}" @selected((string) old('mapping.'.$field, $import->mapping[$field] ?? '') === (string) $index)>{{ $column !== '' ? $column : __('Column :number', ['number' => $index + 1]) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <div>
                <h2 class="h2 mb-3">{{ __('Preview of your file') }}</h2>
                <x-table :stack="false">
                    <thead><tr>@foreach ($preview['header'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ($preview['rows'] as $row)
                            <tr>@foreach ($preview['header'] as $index => $column)<td>{{ $row[$index] ?? '' }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ max(1, count($preview['header'])) }}" class="text-muted">{{ __('The file has no data rows.') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
                <p class="caption mt-2">{{ __('Showing the first rows. Every row is checked during the import; rows with mistakes are skipped and reported.') }}</p>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('imports.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Start import') }}</button>
            </div>
        </form>
    @elseif ($import->status === 'processing')
        <div class="card card-pad">
            <p class="font-medium">{{ __('The import is running.') }}</p>
            <p class="mt-1 text-sm text-muted">{{ __('You can leave this page. You are notified when it is finished.') }}</p>
            <a href="{{ route('imports.show', $import) }}" class="btn btn-secondary mt-4"><x-icon name="refresh" size="14" />{{ __('Refresh') }}</a>
        </div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-stat :label="__('Rows in file')" :value="$import->total_rows" />
            <x-stat :label="__('Imported')" :value="$import->imported_rows" tone="ok" />
            <x-stat :label="__('Skipped')" :value="$import->failed_rows" :tone="$import->failed_rows ? 'danger' : null" />
        </div>

        @if (count($import->errors ?? []))
            <h2 class="h2 mb-3">{{ __('Rows that were skipped') }}</h2>
            <x-table>
                <thead><tr><th>{{ __('Line') }}</th><th>{{ __('What is wrong') }}</th></tr></thead>
                <tbody>
                    @foreach ($import->errors as $error)
                        <tr><td data-label="{{ __('Line') }}" class="num">{{ $error['line'] }}</td><td data-label="{{ __('What is wrong') }}">{{ $error['message'] }}</td></tr>
                    @endforeach
                </tbody>
            </x-table>
            <p class="caption mt-2">{{ __('Fix these lines in your file and import them again. Rows already imported are not affected.') }}</p>
        @else
            <x-alert tone="ok">{{ __('Every row was imported.') }}</x-alert>
        @endif
    @endif
@endsection
