@extends('layouts.app')
@section('title', __('Time tracking'))

@php
    $fmt = fn (int $seconds) => \App\Models\TimeEntry::formatDuration($seconds);
    $allTasks = $projects->flatMap(fn ($project) => $project->tasks);
@endphp

@section('content')
    <x-page-header :title="__('Time tracking')" :subtitle="__('Total for this view: :time', ['time' => $fmt($totalSeconds)])">
        <button type="button" class="btn btn-secondary" data-open="manual-entry">{{ __('Add time by hand') }}</button>
    </x-page-header>

    {{-- Timer: Start / Pause / Stop --}}
    <section class="card card-pad mb-6" aria-labelledby="timer-title">
        <h2 class="sr-only" id="timer-title">{{ __('Timer') }}</h2>
        @if ($active)
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="num text-4xl font-semibold tracking-tight" data-timer="{{ $active->elapsedSeconds() }}" data-running="{{ $active->status === 'running' ? 1 : 0 }}" role="timer">{{ $fmt($active->elapsedSeconds()) }}</p>
                    <p class="mt-1 text-sm text-muted">{{ $active->project?->name ?? __('No project') }}@if ($active->task), {{ $active->task->title }}@endif @if ($active->description)<span class="block">{{ $active->description }}</span>@endif</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($active->status === 'running')
                        <x-action :action="route('time.pause', $active)" class="btn btn-secondary"><x-icon name="pause" size="16" />{{ __('Pause') }}</x-action>
                    @else
                        <x-action :action="route('time.resume', $active)" class="btn btn-secondary"><x-icon name="play" size="16" />{{ __('Resume') }}</x-action>
                    @endif
                    <x-action :action="route('time.stop', $active)" class="btn btn-primary"><x-icon name="stop" size="16" />{{ __('Stop') }}</x-action>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('time.start') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-44 flex-1">
                    <label for="timer-project" class="label">{{ __('Project') }}</label>
                    <select name="project_id" id="timer-project" class="input" data-project-tasks="#timer-task">
                        <option value="">{{ __('No project') }}</option>
                        @foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->name }}</option>@endforeach
                    </select>
                </div>
                <div class="min-w-44 flex-1">
                    <label for="timer-task" class="label">{{ __('Task') }}</label>
                    <select name="project_task_id" id="timer-task" class="input">
                        <option value="">{{ __('No task') }}</option>
                        @foreach ($allTasks as $task)<option value="{{ $task->id }}" data-project="{{ $task->project_id }}">{{ $task->title }}</option>@endforeach
                    </select>
                </div>
                <div class="min-w-56 flex-[2]"><x-input name="description" :label="__('What are you working on?')" id="timer-description" /></div>
                <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" class="check" name="is_billable" value="1" checked>{{ __('Billable') }}</label>
                <button type="submit" class="btn btn-primary"><x-icon name="play" size="16" />{{ __('Start timer') }}</button>
            </form>
            @error('project_id')<p class="field-error">{{ $message }}</p>@enderror
        @endif
    </section>

    <x-filters :search="false">
        <div>
            <label for="f-project" class="sr-only">{{ __('Project') }}</label>
            <select name="project_id" id="f-project" class="input w-auto max-w-56">
                <option value="">{{ __('All projects') }}</option>
                @foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->name }}</option>@endforeach
            </select>
        </div>
        @if ($members->count())
            <div>
                <label for="f-user" class="sr-only">{{ __('Team member') }}</label>
                <select name="user_id" id="f-user" class="input w-auto">
                    <option value="">{{ __('Everyone') }}</option>
                    @foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) request('user_id') === (string) $member->id)>{{ $member->name }}</option>@endforeach
                </select>
            </div>
        @endif
        <div>
            <label for="f-billable" class="sr-only">{{ __('Billing') }}</label>
            <select name="billable" id="f-billable" class="input w-auto">
                <option value="">{{ __('Billable and non-billable') }}</option>
                <option value="1" @selected(request('billable') === '1')>{{ __('Billable') }}</option>
                <option value="0" @selected(request('billable') === '0')>{{ __('Non-billable') }}</option>
            </select>
        </div>
        <div><label for="f-from" class="label">{{ __('From') }}</label><input type="date" name="from" id="f-from" value="{{ request('from') }}" class="input w-auto"></div>
        <div><label for="f-to" class="label">{{ __('To') }}</label><input type="date" name="to" id="f-to" value="{{ request('to') }}" class="input w-auto"></div>
    </x-filters>

    @if ($entries->count())
        <x-table>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Who') }}</th><th>{{ __('Project') }}</th><th>{{ __('Work') }}</th><th class="text-end">{{ __('Time') }}</th><th class="text-end">{{ __('Rate') }}</th><th>{{ __('Billing') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td data-label="{{ __('Date') }}">{{ tdate($entry->entry_date) }}</td>
                        <td data-label="{{ __('Who') }}">{{ $entry->user?->name }}</td>
                        <td data-label="{{ __('Project') }}">@if ($entry->project)<a class="link" href="{{ route('projects.show', $entry->project) }}">{{ $entry->project->name }}</a>@else — @endif</td>
                        <td data-label="{{ __('Work') }}">{{ $entry->task?->title }}@if ($entry->description)<span class="caption block">{{ $entry->description }}</span>@endif</td>
                        <td data-label="{{ __('Time') }}" class="num text-end">{{ $fmt($entry->elapsedSeconds()) }}@if ($entry->status !== 'stopped') <x-badge :status="$entry->status" />@endif</td>
                        <td data-label="{{ __('Rate') }}" class="num text-end">{{ (float) $entry->hourly_rate > 0 ? money($entry->hourly_rate) : '—' }}</td>
                        <td data-label="{{ __('Billing') }}">
                            @if ($entry->invoice_id)<a class="badge badge-ok" href="{{ route('invoices.show', $entry->invoice_id) }}">{{ __('Invoiced') }}</a>
                            @elseif ($entry->is_billable)<x-badge tone="warn">{{ __('Billable') }}</x-badge>
                            @else<x-badge tone="neutral">{{ __('Not billable') }}</x-badge>@endif
                        </td>
                        <td class="text-end">
                            @unless ($entry->invoice_id)
                                <x-action :action="route('time.destroy', $entry)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete this time entry?')" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $entries->links() }}
    @else
        <div class="card"><x-empty :title="__('No time tracked yet')" :text="__('Start the timer when you begin working, or add time by hand afterwards. Billable time on a project becomes an invoice line in one click.')" /></div>
    @endif

    <x-modal id="manual-entry" :title="__('Add time by hand')" :auto-open="$errors->hasAny(['hours', 'entry_date'])">
        <form method="POST" action="{{ route('time.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="entry_date" type="date" :label="__('Date')" :value="today_local()" required />
                <x-input name="hours" type="number" step="0.01" min="0.01" max="24" :label="__('Hours')" required :hint="__('1.5 means one and a half hours')" />
                <div>
                    <label for="manual-project" class="label">{{ __('Project') }}</label>
                    <select name="project_id" id="manual-project" class="input" data-project-tasks="#manual-task">
                        <option value="">{{ __('No project') }}</option>
                        @foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="manual-task" class="label">{{ __('Task') }}</label>
                    <select name="project_task_id" id="manual-task" class="input">
                        <option value="">{{ __('No task') }}</option>
                        @foreach ($allTasks as $task)<option value="{{ $task->id }}" data-project="{{ $task->project_id }}">{{ $task->title }}</option>@endforeach
                    </select>
                </div>
            </div>
            <x-input name="description" :label="__('What did you work on?')" id="manual-description" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="hourly_rate" type="number" step="any" min="0" :label="__('Hourly rate')" :hint="__('Leave empty to use the project rate.')" />
                <div class="pt-7"><x-checkbox name="is_billable" :label="__('Billable')" checked id="manual-billable" /></div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Add time') }}</button>
            </div>
        </form>
    </x-modal>
@endsection
