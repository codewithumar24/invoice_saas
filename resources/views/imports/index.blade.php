@extends('layouts.app')
@section('title', __('Import'))

@section('content')
    <x-page-header :title="__('Import from CSV')" :subtitle="__('Upload, match the columns, check the preview, import.')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Upload a file')">
            <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-select name="type" :label="__('What are you importing?')" :options="collect($types)->filter(fn ($t) => auth()->user()->hasPermission($t.'.create'))->mapWithKeys(fn ($t) => [$t => __(ucfirst($t))])->all()" required />
                <div>
                    <label for="f-file" class="label">{{ __('CSV file') }}</label>
                    <input type="file" name="file" id="f-file" accept=".csv,text/csv,text/plain" class="input" required>
                    <p class="field-hint">{{ __('The first row must contain the column names. Up to 10 MB.') }}</p>
                    @error('file')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary w-full">{{ __('Upload and continue') }}</button>
            </form>
        </x-card>

        <div class="lg:col-span-2">
            <h2 class="h2 mb-3">{{ __('Earlier imports') }}</h2>
            @if ($imports->count())
                <x-table>
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('By') }}</th><th class="text-end">{{ __('Imported') }}</th><th class="text-end">{{ __('Failed') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($imports as $import)
                            <tr>
                                <td data-label="{{ __('Date') }}"><a class="link" href="{{ route('imports.show', $import) }}">{{ tdate($import->created_at, 'd M Y, H:i') }}</a></td>
                                <td data-label="{{ __('Type') }}">{{ __(ucfirst($import->type)) }}</td>
                                <td data-label="{{ __('By') }}">{{ $import->user?->name }}</td>
                                <td data-label="{{ __('Imported') }}" class="num text-end">{{ $import->imported_rows }}</td>
                                <td data-label="{{ __('Failed') }}" class="num text-end {{ $import->failed_rows ? 'text-danger' : '' }}">{{ $import->failed_rows }}</td>
                                <td data-label="{{ __('Status') }}"><x-badge :status="$import->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
                {{ $imports->links() }}
            @else
                <div class="card"><x-empty :title="__('No imports yet')" :text="__('Bring customers, products or expenses over from a spreadsheet or another system.')" /></div>
            @endif
        </div>
    </div>
@endsection
