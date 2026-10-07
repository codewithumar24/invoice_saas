@extends('layouts.admin')
@section('title', __('Email templates'))

@section('content')
    <x-page-header :title="__('Email templates')" :subtitle="__('Platform defaults for every email. Workspaces can override the emails their customers receive.')" />

    <ul class="card divide-y divide-line">
        @foreach ($templates as $key => $template)
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <div class="min-w-0">
                    <p class="text-sm font-medium">{{ $template['name'] }} @if (in_array($key, $customised))<span class="badge badge-brand">{{ __('Customised') }}</span>@endif</p>
                    <p class="caption truncate"><code>{{ $key }}</code> {{ $template['subject'] }}</p>
                </div>
                <a href="{{ route('admin.email-templates.edit', $key) }}" class="btn btn-secondary btn-sm">{{ __('Edit') }}</a>
            </li>
        @endforeach
    </ul>
@endsection
