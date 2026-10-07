@extends('layouts.app')
@section('title', __($name))

@section('content')
    @component('settings._layout')
        <x-card :title="__($name)">
            <x-slot:actions><a href="{{ route('settings.email-templates') }}" class="link text-sm">{{ __('All templates') }}</a></x-slot:actions>
            <form method="POST" action="{{ route('settings.email-templates.update', $key) }}" class="space-y-4">
                @csrf @method('PUT')
                <x-input name="subject" :label="__('Subject')" :value="$subject" required />
                <x-textarea name="body" :label="__('Body')" :value="$body" rows="12" required :hint="__('Plain text. Leave an empty line between paragraphs. Links become clickable.')" />
                <div>
                    <p class="label">{{ __('Variables you can use') }}</p>
                    <p class="flex flex-wrap gap-1.5">
                        @foreach ($variables as $variable)
                            <code class="rounded bg-raised px-1.5 py-0.5 text-xs">{{ '{'.'{'.$variable.'}'.'}' }}</code>
                        @endforeach
                    </p>
                </div>
                @can('settings.update')
                    <div class="flex flex-wrap justify-between gap-2">
                        @if ($isCustom)
                            <button type="submit" name="reset" value="1" class="btn btn-secondary" formnovalidate data-confirm="{{ __('Go back to the standard wording for this email?') }}" data-confirm-label="{{ __('Reset') }}">{{ __('Reset to default') }}</button>
                        @else
                            <span></span>
                        @endif
                        <button type="submit" class="btn btn-primary">{{ __('Save template') }}</button>
                    </div>
                @endcan
            </form>
        </x-card>
    @endcomponent
@endsection
