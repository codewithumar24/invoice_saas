@extends('layouts.app')
@section('title', $project->exists ? __('Edit project') : __('New project'))

@section('content')
    <x-page-header :title="$project->exists ? __('Edit :name', ['name' => $project->name]) : __('New project')" :back="$project->exists ? route('projects.show', $project) : route('projects.index')" />

    <form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="card card-pad max-w-3xl space-y-4">
        @csrf
        @if ($project->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-input name="name" :label="__('Project name')" :value="$project->name" required autofocus />
            <x-select name="customer_id" :label="__('Customer')" :options="$customers->pluck('name', 'id')->all()" :value="$project->customer_id" :placeholder="__('Internal project')" :hint="__('Needed to invoice the project.')" />
            <x-input name="start_date" type="date" :label="__('Start date')" :value="$project->start_date" />
            <x-input name="deadline" type="date" :label="__('Deadline')" :value="$project->deadline" />
            <x-input name="budget" type="number" step="any" min="0" :label="__('Budget (:currency)', ['currency' => tenant()->currency])" :value="(float) $project->budget ?: null" />
            <x-input name="hourly_rate" type="number" step="any" min="0" :label="__('Hourly rate')" :value="(float) $project->hourly_rate ?: null" :hint="__('Used when billable time is invoiced.')" />
            <x-select name="status" :label="__('Status')" :options="collect(\App\Models\Project::STATUSES)->mapWithKeys(fn ($s) => [$s => status_label($s)])->all()" :value="$project->status" required />
        </div>
        <x-textarea name="description" :label="__('Description')" :value="$project->description" />
        @error('limit')<p class="field-error">{{ $message }}</p>@enderror
        <div class="flex justify-end gap-2">
            <a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $project->exists ? __('Save project') : __('Create project') }}</button>
        </div>
    </form>
@endsection
