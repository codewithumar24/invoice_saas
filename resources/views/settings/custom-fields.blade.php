@extends('layouts.app')
@section('title', __('Custom fields'))

@php $entities = ['customer' => __('Customers'), 'invoice' => __('Invoices'), 'product' => __('Products & services')]; @endphp

@section('content')
    @component('settings._layout')
        <x-card :title="__('Custom fields')" :padded="false">
            <p class="px-4 pt-4 text-sm text-muted sm:px-6">{{ __('Add your own fields, such as Industry or VAT Number for customers, Purchase Order or Project Code for invoices, Manufacturer or Warranty for products.') }}</p>
            @if ($fields->flatten()->count())
                <table class="table table-stack mt-3">
                    <thead><tr><th>{{ __('Field') }}</th><th>{{ __('Used on') }}</th><th>{{ __('Type') }}</th><th>{{ __('Options') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($fields as $entity => $group)
                            @foreach ($group as $field)
                                <tr>
                                    <td data-label="{{ __('Field') }}" class="font-medium">{{ $field->label }}@if ($field->is_required) <span class="badge badge-neutral">{{ __('Required') }}</span>@endif @if ($field->show_on_pdf) <span class="badge badge-info">{{ __('On PDF') }}</span>@endif</td>
                                    <td data-label="{{ __('Used on') }}">{{ $entities[$entity] ?? $entity }}</td>
                                    <td data-label="{{ __('Type') }}">{{ status_label($field->type) }}</td>
                                    <td data-label="{{ __('Options') }}" class="text-muted">{{ $field->options ? implode(', ', $field->options) : '—' }}</td>
                                    <td class="text-end">@can('settings.update')<x-action :action="route('settings.custom-fields.destroy', $field)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete the field :name and every value saved in it?', ['name' => $field->label])" :confirm-label="__('Delete field')">{{ __('Delete') }}</x-action>@endcan</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            @else
                <x-empty :title="__('No custom fields yet')" />
            @endif
        </x-card>

        @can('settings.update')
            <x-card :title="__('Add a field')">
                <form method="POST" action="{{ route('settings.custom-fields.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-select name="entity" :label="__('Used on')" :options="$entities" required />
                        <x-input name="label" :label="__('Field name')" required />
                        <x-select name="type" :label="__('Type')" :options="['text' => __('Text'), 'number' => __('Number'), 'date' => __('Date'), 'select' => __('Choice from a list')]" required />
                    </div>
                    <div data-show-when="type=select"><x-input name="options" :label="__('Choices')" :hint="__('Separate with commas')" /></div>
                    <div class="flex flex-wrap gap-6">
                        <x-checkbox name="is_required" :label="__('Required')" />
                        <div data-show-when="entity=invoice"><x-checkbox name="show_on_pdf" :label="__('Show on the invoice PDF')" /></div>
                    </div>
                    <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Add field') }}</button></div>
                </form>
            </x-card>
        @endcan
    @endcomponent
@endsection
