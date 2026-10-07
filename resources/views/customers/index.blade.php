@extends('layouts.app')
@section('title', __('Customers'))

@section('content')
    <x-page-header :title="__('Customers')">
        @can('customers.create')
            <a href="{{ route('imports.index') }}" class="btn btn-secondary">{{ __('Import CSV') }}</a>
            <a href="{{ route('customers.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('Add customer') }}</a>
        @endcan
    </x-page-header>

    <x-filters :placeholder="__('Name, email, company or phone')">
        <div>
            <label for="f-status" class="sr-only">{{ __('Status') }}</label>
            <select name="status" id="f-status" class="input w-auto">
                <option value="">{{ __('Active') }}</option>
                <option value="archived" @selected(request('status') === 'archived')>{{ __('Archived') }}</option>
            </select>
        </div>
        @if ($tags->count())
            <div>
                <label for="f-tag" class="sr-only">{{ __('Tag') }}</label>
                <select name="tag" id="f-tag" class="input w-auto">
                    <option value="">{{ __('All tags') }}</option>
                    @foreach ($tags as $tag)<option value="{{ $tag->id }}" @selected((string) request('tag') === (string) $tag->id)>{{ $tag->name }}</option>@endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="f-balance" class="sr-only">{{ __('Balance') }}</label>
            <select name="balance" id="f-balance" class="input w-auto">
                <option value="">{{ __('Any balance') }}</option>
                <option value="owing" @selected(request('balance') === 'owing')>{{ __('Owes money') }}</option>
            </select>
        </div>
    </x-filters>

    @if ($customers->count())
        <form method="POST" action="{{ route('customers.bulk') }}" data-bulk>
            @csrf
            @component('partials.bulk-bar', ['actions' => ['archive' => __('Archive'), 'tag' => __('Add tag'), 'export' => __('Export CSV')]])
                <input type="text" name="tag" class="input w-40 py-1.5" placeholder="{{ __('Tag name') }}" aria-label="{{ __('Tag name') }}" data-show-when="action=tag">
            @endcomponent

            <x-table id="customers-table">
                <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" class="check" data-bulk-all aria-label="{{ __('Select all') }}"></th>
                        <th><x-sort column="name" :label="__('Customer')" /></th>
                        <th>{{ __('Contact') }}</th>
                        <th>{{ __('Tags') }}</th>
                        <th class="text-end"><x-sort column="balance" :label="__('Outstanding')" /></th>
                        <th><x-sort column="last_activity_at" :label="__('Last activity')" /></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td><input type="checkbox" class="check" name="ids[]" value="{{ $customer->id }}" data-bulk-item aria-label="{{ __('Select :name', ['name' => $customer->name]) }}"></td>
                            <td data-label="{{ __('Customer') }}">
                                <a href="{{ route('customers.show', $customer) }}" class="font-medium hover:text-brand">{{ $customer->name }}</a>
                                @if ($customer->company)<span class="caption block">{{ $customer->company }}</span>@endif
                            </td>
                            <td data-label="{{ __('Contact') }}">{{ $customer->email ?: '—' }}@if ($customer->phone)<span class="caption block">{{ $customer->phone }}</span>@endif</td>
                            <td data-label="{{ __('Tags') }}">
                                <span class="flex flex-wrap justify-end gap-1 md:justify-start">
                                    @foreach ($customer->tags as $tag)<span class="badge badge-neutral"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $tag->color }}"></span>{{ $tag->name }}</span>@endforeach
                                </span>
                            </td>
                            <td data-label="{{ __('Outstanding') }}" class="num text-end {{ $customer->balance > 0 ? 'font-medium' : 'text-muted' }}">{{ money($customer->balance) }}</td>
                            <td data-label="{{ __('Last activity') }}" class="text-muted">{{ $customer->last_activity_at?->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </form>
        {{ $customers->links() }}
    @else
        <div class="card">
            @if (request()->hasAny(['search', 'status', 'tag', 'balance']))
                <x-empty :title="__('No customers match these filters')" :text="__('Try a different search or clear the filters.')">
                    <a href="{{ route('customers.index') }}" class="btn btn-secondary">{{ __('Clear filters') }}</a>
                </x-empty>
            @else
                <x-empty :title="__('No customers yet')" :text="__('Customers are the people and companies you invoice. Add one to create your first quote or invoice.')">
                    @can('customers.create')<a href="{{ route('customers.create') }}" class="btn btn-primary">{{ __('Add your first customer') }}</a>@endcan
                </x-empty>
            @endif
        </div>
    @endif
@endsection
