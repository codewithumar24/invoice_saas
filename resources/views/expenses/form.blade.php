@extends('layouts.app')
@section('title', $expense->exists ? __('Edit expense') : __('Add expense'))

@section('content')
    <x-page-header :title="$expense->exists ? __('Edit expense') : __('Add expense')" :back="route('expenses.index')">
        @if ($expense->exists)
            @can('expenses.delete')
                <x-action :action="route('expenses.destroy', $expense)" method="DELETE" class="btn btn-danger" :confirm="__('Delete this expense? It is removed from reports but kept in the audit log.')" :confirm-label="__('Delete expense')">{{ __('Delete') }}</x-action>
            @endcan
        @endif
    </x-page-header>

    <form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @if ($expense->exists) @method('PUT') @endif

        <x-card :title="__('Expense')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="amount" type="number" step="any" min="0.01" :label="__('Amount')" :value="$expense->exists ? (float) $expense->amount : null" required autofocus />
                <x-select name="currency" :label="__('Currency')" :options="$currencies" :value="$expense->currency" required />
                <x-input name="expense_date" type="date" :label="__('Date')" :value="$expense->expense_date" required />
                <div>
                    <x-select name="category_id" :label="__('Category')" :options="$categories->pluck('name', 'id')->all()" :value="$expense->category_id" :placeholder="__('No category yet')" />
                    <button type="button" class="link mt-1 text-sm" data-open="new-category">{{ __('New category') }}</button>
                </div>
                <x-input name="vendor" :label="__('Vendor')" :value="$expense->vendor" />
                <x-input name="payment_method" :label="__('Payment method')" :value="$expense->payment_method" placeholder="{{ __('Card, cash, bank transfer') }}" />
                <x-input name="tax_amount" type="number" step="any" min="0" :label="__('Tax included in the amount')" :value="(float) $expense->tax_amount > 0 ? (float) $expense->tax_amount : null" />
                <x-input name="reference" :label="__('Reference')" :value="$expense->reference" />
                @if ($projects->count())<x-select name="project_id" :label="__('Project')" :options="$projects->pluck('name', 'id')->all()" :value="$expense->project_id" :placeholder="__('None')" />@endif
                @if ($branches->count())<x-select name="branch_id" :label="__('Branch')" :options="$branches->pluck('name', 'id')->all()" :value="$expense->branch_id" :placeholder="__('None')" />@endif
                <div class="sm:col-span-2"><x-textarea name="notes" :label="__('Notes')" :value="$expense->notes" rows="2" /></div>
                <div class="sm:col-span-2"><x-input name="tags" :label="__('Tags')" :value="$expense->exists ? $expense->tags->pluck('name')->implode(', ') : null" :hint="__('Separate with commas')" /></div>
            </div>
        </x-card>

        <x-card :title="__('Repeats')">
            <x-switch name="is_recurring" :label="__('This expense repeats')" :hint="__('Used by the cash-flow forecast. Record each occurrence when it happens.')" :checked="$expense->is_recurring" />
            <div class="mt-4 max-w-xs" data-show-when="is_recurring=1">
                <x-select name="recurring_frequency" :label="__('How often')" :options="['weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'quarterly' => __('Quarterly'), 'yearly' => __('Yearly')]" :value="$expense->recurring_frequency ?? 'monthly'" />
            </div>
        </x-card>

        <x-card :title="__('Receipts')">
            @if ($expense->exists && $expense->receipts->count())
                <ul class="mb-4 divide-y divide-line rounded-md border border-line">
                    @foreach ($expense->receipts as $receipt)
                        <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                            <a class="link truncate" href="{{ route('expenses.receipts.show', $receipt) }}">{{ $receipt->original_name }}</a>
                            <button type="submit" form="delete-receipt-{{ $receipt->id }}" class="btn btn-ghost btn-sm text-danger">{{ __('Remove') }}</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <label for="f-receipts" class="label">{{ __('Add receipts') }}</label>
            <input type="file" name="receipts[]" id="f-receipts" multiple accept="image/png,image/jpeg,image/webp,application/pdf" class="input">
            <p class="field-hint">{{ __('Images or PDF, up to :size MB each. Receipts are stored privately and only your team can open them.', ['size' => config('saas.upload.max_kb') / 1024]) }}</p>
            @foreach ($errors->get('receipts.*') as $messages)<p class="field-error">{{ $messages[0] }}</p>@endforeach
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('expenses.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $expense->exists ? __('Save expense') : __('Add expense') }}</button>
        </div>
    </form>

    @if ($expense->exists)
        @foreach ($expense->receipts as $receipt)
            <form method="POST" action="{{ route('expenses.receipts.destroy', $receipt) }}" id="delete-receipt-{{ $receipt->id }}" data-confirm="{{ __('Remove this receipt?') }}" data-confirm-label="{{ __('Remove') }}">@csrf @method('DELETE')</form>
        @endforeach
    @endif

    <x-modal id="new-category" :title="__('New expense category')">
        <form method="POST" action="{{ route('expenses.categories.store') }}" class="space-y-4">
            @csrf
            <x-input name="name" :label="__('Category name')" required id="category-name" />
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Add category') }}</button>
            </div>
        </form>
    </x-modal>
@endsection
