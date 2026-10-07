@extends('layouts.app')
@section('title', __('Projects'))

@section('content')
    <x-page-header :title="__('Projects')">
        @can('projects.manage')<a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('New project') }}</a>@endcan
    </x-page-header>

    <x-filters :placeholder="__('Project name')">
        <div>
            <label for="f-status" class="sr-only">{{ __('Status') }}</label>
            <select name="status" id="f-status" class="input w-auto">
                <option value="">{{ __('All except archived') }}</option>
                @foreach (\App\Models\Project::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ status_label($status) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="f-customer" class="sr-only">{{ __('Customer') }}</label>
            <select name="customer_id" id="f-customer" class="input w-auto max-w-48">
                <option value="">{{ __('All customers') }}</option>
                @foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->name }}</option>@endforeach
            </select>
        </div>
    </x-filters>

    @if ($projects->count())
        <x-table>
            <thead><tr><th><x-sort column="name" :label="__('Project')" /></th><th>{{ __('Customer') }}</th><th><x-sort column="deadline" :label="__('Deadline')" /></th><th class="text-end">{{ __('Open tasks') }}</th><th class="text-end"><x-sort column="budget" :label="__('Budget')" /></th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
                @foreach ($projects as $project)
                    <tr>
                        <td data-label="{{ __('Project') }}"><a href="{{ route('projects.show', $project) }}" class="font-medium hover:text-brand">{{ $project->name }}</a></td>
                        <td data-label="{{ __('Customer') }}">{{ $project->customer?->name ?? '—' }}</td>
                        <td data-label="{{ __('Deadline') }}" class="{{ $project->deadline && $project->deadline->lt(today_local()) && $project->status === 'active' ? 'text-danger' : '' }}">{{ tdate($project->deadline) }}</td>
                        <td data-label="{{ __('Open tasks') }}" class="num text-end">{{ $project->open_tasks_count }} / {{ $project->tasks_count }}</td>
                        <td data-label="{{ __('Budget') }}" class="num text-end">{{ (float) $project->budget > 0 ? money($project->budget) : '—' }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :status="$project->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $projects->links() }}
    @else
        <div class="card">
            <x-empty :title="request()->except('page') ? __('No projects match these filters') : __('No projects yet')" :text="__('A project groups tasks, tracked time and expenses for a customer, and turns billable time into an invoice.')">
                @can('projects.manage')<a href="{{ route('projects.create') }}" class="btn btn-primary">{{ __('Create your first project') }}</a>@endcan
            </x-empty>
        </div>
    @endif
@endsection
