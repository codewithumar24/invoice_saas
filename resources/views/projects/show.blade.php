@extends('layouts.app')
@section('title', $project->name)

@php
    $canManage = auth()->user()->can('update', $project);
    $fmt = fn (int $seconds) => \App\Models\TimeEntry::formatDuration($seconds);
    $columns = ['todo' => __('To do'), 'in_progress' => __('In progress'), 'done' => __('Done')];
@endphp

@section('content')
    <x-page-header :title="$project->name" :subtitle="$project->customer?->name ?? __('Internal project')" :back="route('projects.index')">
        <x-badge :status="$project->status" class="text-sm" />
        @if ($stats['unbilled_seconds'] > 0 && $project->customer_id)
            @can('invoices.create')
                <x-action :action="route('projects.invoice', $project)" class="btn btn-primary" :confirm="__('Create a draft invoice from :time of billable time?', ['time' => $fmt($stats['unbilled_seconds'])])" :confirm-label="__('Create invoice')">{{ __('Invoice billable time') }}</x-action>
            @endcan
        @endif
        @if ($canManage)
            <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary">{{ __('Edit') }}</a>
            <x-action :action="route('projects.destroy', $project)" method="DELETE" class="btn btn-danger" :confirm="__('Delete this project? Projects with time or invoices are archived instead.')" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>
        @endif
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat :label="__('Time tracked')" :value="$fmt($stats['seconds'])" />
        <x-stat :label="__('Not yet invoiced')" :value="$fmt($stats['unbilled_seconds'])" :note="__('Billable time')" />
        <x-stat :label="__('Invoiced')" :value="money($stats['invoiced'])" :note="(float) $project->budget > 0 ? __('Budget: :amount', ['amount' => money($project->budget)]) : null" />
        <x-stat :label="__('Expenses')" :value="money($stats['expenses'])" />
        <x-stat :label="__('Profit')" :value="money($stats['profit'])" :tone="$stats['profit'] < 0 ? 'danger' : null" />
    </div>

    @if ($project->description)<p class="mb-6 max-w-3xl whitespace-pre-line text-sm text-muted">{{ $project->description }}</p>@endif

    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="h2">{{ __('Tasks') }}</h2>
        @if ($canManage)<button type="button" class="btn btn-secondary btn-sm" data-open="new-task"><x-icon name="plus" size="14" />{{ __('Add task') }}</button>@endif
    </div>

    @if ($project->tasks->count())
        <div class="mb-8 grid gap-4 md:grid-cols-3">
            @foreach ($columns as $status => $label)
                <section class="card" aria-label="{{ $label }}">
                    <div class="card-head"><h3 class="h3">{{ $label }}</h3><span class="num caption">{{ $project->tasks->where('status', $status)->count() }}</span></div>
                    <ul class="divide-y divide-line">
                        @forelse ($project->tasks->where('status', $status) as $task)
                            <li class="px-4 py-3 text-sm">
                                <p class="font-medium">{{ $task->title }}</p>
                                <p class="caption mt-0.5">
                                    {{ $task->assignee?->name ?? __('Unassigned') }}@if ($task->due_date), {{ __('due :date', ['date' => tdate($task->due_date)]) }}@endif
                                    @if ($task->priority === 'high') <x-badge tone="danger">{{ __('High') }}</x-badge>@endif
                                </p>
                                @if ($canManage)
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach ($columns as $target => $targetLabel)
                                            @continue($target === $status)
                                            <form method="POST" action="{{ route('tasks.update', $task) }}">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $target }}"><button class="btn btn-ghost btn-sm">{{ __('Move to :column', ['column' => $targetLabel]) }}</button></form>
                                        @endforeach
                                        <x-action :action="route('tasks.destroy', $task)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete this task?')" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>
                                    </div>
                                @endif
                            </li>
                        @empty
                            <li class="px-4 py-3 text-sm text-muted">{{ __('Nothing here.') }}</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    @else
        <div class="card mb-8"><x-empty :title="__('No tasks yet')" :text="__('Break the project into tasks, assign them and track time against each one.')" /></div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Time entries')" class="lg:col-span-2" :padded="false">
            <x-slot:actions><a href="{{ route('time.index', ['project_id' => $project->id]) }}" class="link text-sm">{{ __('Track time') }}</a></x-slot:actions>
            @if ($entries->count())
                <table class="table table-stack">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Who') }}</th><th>{{ __('Work') }}</th><th class="text-end">{{ __('Time') }}</th><th>{{ __('Billing') }}</th></tr></thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td data-label="{{ __('Date') }}">{{ tdate($entry->entry_date) }}</td>
                                <td data-label="{{ __('Who') }}">{{ $entry->user?->name }}</td>
                                <td data-label="{{ __('Work') }}">{{ $entry->task?->title ?? $entry->description ?? '—' }}</td>
                                <td data-label="{{ __('Time') }}" class="num text-end">{{ $fmt($entry->elapsedSeconds()) }}</td>
                                <td data-label="{{ __('Billing') }}">
                                    @if ($entry->invoice_id)<a class="badge badge-ok" href="{{ route('invoices.show', $entry->invoice_id) }}">{{ __('Invoiced') }}</a>
                                    @elseif ($entry->is_billable)<x-badge tone="warn">{{ __('Billable') }}</x-badge>
                                    @else<x-badge tone="neutral">{{ __('Not billable') }}</x-badge>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="card-pad text-sm text-muted">{{ __('Time tracked on this project appears here.') }}</p>
            @endif
        </x-card>

        <x-card :title="__('Invoices')" :padded="false">
            @if ($project->invoices->count())
                <ul class="divide-y divide-line">
                    @foreach ($project->invoices as $invoice)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm sm:px-6">
                            <a class="link num" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                            <span class="num">{{ money($invoice->total, $invoice->currency) }}</span>
                            <x-badge :status="$invoice->displayStatus()" />
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="card-pad text-sm text-muted">{{ __('Invoices created for this project are listed here.') }}</p>
            @endif
        </x-card>
    </div>

    @if ($canManage)
        <x-modal id="new-task" :title="__('Add task')" :auto-open="$errors->hasAny(['title', 'assigned_to', 'due_date'])">
            <form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="space-y-4">
                @csrf
                <x-input name="title" :label="__('Task')" required />
                <x-textarea name="description" :label="__('Details')" rows="2" id="task-description" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select name="assigned_to" :label="__('Assigned to')" :options="$members->pluck('name', 'id')->all()" :placeholder="__('Unassigned')" />
                    <x-input name="due_date" type="date" :label="__('Due date')" />
                    <x-select name="priority" :label="__('Priority')" :options="['low' => __('Low'), 'normal' => __('Normal'), 'high' => __('High')]" value="normal" />
                    <x-select name="status" :label="__('Status')" :options="$columns" value="todo" id="task-status" />
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Add task') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
@endsection
