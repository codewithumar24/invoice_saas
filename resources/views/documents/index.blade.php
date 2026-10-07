@extends('layouts.app')
@section('title', __('Documents'))

@php $canManage = auth()->user()->hasPermission('documents.manage'); $current = request('category'); @endphp

@section('content')
    <x-page-header :title="__('Documents')" :subtitle="__('Invoices, receipts, quotes, contracts and customer files in one place.')">
        @if ($canManage)<button type="button" class="btn btn-primary" data-open="upload-document"><x-icon name="plus" size="16" />{{ __('Upload document') }}</button>@endif
    </x-page-header>

    <nav class="mb-4 flex gap-6 overflow-x-auto border-b border-line" aria-label="{{ __('Category') }}">
        <a href="{{ route('documents.index') }}" class="tab" @if (! $current) aria-current="page" @endif>{{ __('All') }}</a>
        @foreach ($categories as $key => $label)
            <a href="{{ route('documents.index', ['category' => $key]) }}" class="tab" @if ($current === $key) aria-current="page" @endif>{{ __($label) }}</a>
        @endforeach
        @if (feature('expenses'))<a href="{{ route('documents.index', ['category' => 'expense_receipts']) }}" class="tab" @if ($current === 'expense_receipts') aria-current="page" @endif>{{ __('Expense receipts') }}</a>@endif
    </nav>

    <x-filters :placeholder="__('File name')">
        @if ($current)<input type="hidden" name="category" value="{{ $current }}">@endif
    </x-filters>

    @if ($receipts)
        @if ($receipts->count())
            <x-table>
                <thead><tr><th>{{ __('File') }}</th><th>{{ __('Expense') }}</th><th>{{ __('Uploaded') }}</th><th class="text-end">{{ __('Size') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($receipts as $receipt)
                        <tr>
                            <td data-label="{{ __('File') }}" class="font-medium">{{ $receipt->original_name }}</td>
                            <td data-label="{{ __('Expense') }}">@if ($receipt->expense){{ $receipt->expense->vendor ?: __('Expense') }}, {{ money($receipt->expense->amount, $receipt->expense->currency) }}@else — @endif</td>
                            <td data-label="{{ __('Uploaded') }}">{{ tdate($receipt->created_at) }}</td>
                            <td data-label="{{ __('Size') }}" class="num text-end">{{ number_format($receipt->size / 1024) }} KB</td>
                            <td class="text-end"><a href="{{ route('expenses.receipts.show', $receipt) }}" class="btn btn-secondary btn-sm">{{ __('Download') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
            {{ $receipts->links() }}
        @else
            <div class="card"><x-empty :title="__('No expense receipts yet')" :text="__('Receipts attached to expenses are listed here.')" /></div>
        @endif
    @elseif ($documents->count())
        <x-table>
            <thead><tr><th>{{ __('File') }}</th><th>{{ __('Category') }}</th><th>{{ __('Belongs to') }}</th><th>{{ __('Uploaded') }}</th><th class="text-end">{{ __('Size') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($documents as $document)
                    <tr>
                        <td data-label="{{ __('File') }}" class="font-medium">{{ $document->original_name }}@if ($document->visible_to_customer) <span class="badge badge-info">{{ __('Shared with customer') }}</span>@endif</td>
                        <td data-label="{{ __('Category') }}">{{ __($categories[$document->category] ?? status_label($document->category)) }}</td>
                        <td data-label="{{ __('Belongs to') }}">{{ $document->attachable?->name ?? $document->attachable?->number ?? '—' }}</td>
                        <td data-label="{{ __('Uploaded') }}">{{ tdate($document->created_at) }}@if ($document->uploader)<span class="caption block">{{ $document->uploader->name }}</span>@endif</td>
                        <td data-label="{{ __('Size') }}" class="num text-end">{{ number_format($document->size / 1024) }} KB</td>
                        <td class="text-end">
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('documents.download', $document) }}" class="btn btn-secondary btn-sm">{{ __('Download') }}</a>
                                @if ($canManage)<x-action :action="route('documents.destroy', $document)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete :name?', ['name' => $document->original_name])" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>@endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $documents->links() }}
    @else
        <div class="card">
            <x-empty :title="request('search') || $current ? __('No documents here') : __('No documents yet')" :text="__('Invoice PDFs are stored here when you send them. Upload contracts and other files to keep everything about your business together.')">
                @if ($canManage)<button type="button" class="btn btn-primary" data-open="upload-document">{{ __('Upload a document') }}</button>@endif
            </x-empty>
        </div>
    @endif

    @if ($canManage)
        <x-modal id="upload-document" :title="__('Upload document')" :auto-open="$errors->hasAny(['file', 'category'])">
            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label for="f-file" class="label">{{ __('File') }}</label>
                    <input type="file" name="file" id="f-file" class="input" required>
                    <p class="field-hint">{{ __('Up to :size MB. Images, PDF, CSV and Office documents.', ['size' => config('saas.upload.max_kb') / 1024]) }}</p>
                    @error('file')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-select name="category" :label="__('Category')" :options="collect($categories)->except('invoices')->map(fn ($label) => __($label))->all()" value="general" required id="doc-category" />
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
@endsection
