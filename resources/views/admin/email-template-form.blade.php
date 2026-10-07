@extends('layouts.admin')
@section('title', $name)

@section('content')
    <x-page-header :title="$name" :subtitle="$key" :back="route('admin.email-templates')" />

    <form method="POST" action="{{ route('admin.email-templates.update', $key) }}" class="card card-pad max-w-3xl space-y-4">
        @csrf @method('PUT')
        <x-input name="subject" :label="__('Subject')" :value="$subject" required />
        <x-textarea name="body" :label="__('Body')" :value="$body" rows="14" required :hint="__('Plain text with variables in double curly braces. Leave an empty line between paragraphs.')" />
        <div class="flex flex-wrap justify-between gap-2">
            @if ($isCustom)
                <button type="submit" name="reset" value="1" class="btn btn-secondary" formnovalidate data-confirm="{{ __('Go back to the built-in wording?') }}" data-confirm-label="{{ __('Reset') }}">{{ __('Reset to built-in') }}</button>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary">{{ __('Save template') }}</button>
        </div>
    </form>
@endsection
