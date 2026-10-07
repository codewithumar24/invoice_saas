@extends('layouts.app')
@section('title', __('Modules'))

@php
    // Modules that also need a plan feature
    $needsPlan = ['inventory' => 'inventory', 'projects' => 'projects', 'automation' => 'automation', 'business_health' => 'business_health'];
    $plan = tenant()->plan?->load('features');
@endphp

@section('content')
    @component('settings._layout', ['tenant' => $tenant, 'needsPlan' => $needsPlan, 'plan' => $plan])
        <form method="POST" action="{{ route('settings.modules.update') }}">
            @csrf @method('PUT')
            <x-card :title="__('Modules')">
                <p class="mb-4 text-sm text-muted">{{ __('Switch on only what you use. Modules that are off disappear from the menu; their data is kept and returns when you switch them on again.') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (config('saas.modules') as $key => $label)
                        @php
                            $core = in_array($key, ['invoicing', 'payments']);
                            $locked = isset($needsPlan[$key]) && $plan && $plan->featureValue($needsPlan[$key]) !== '1';
                        @endphp
                        <label class="flex items-start gap-3 rounded-md border border-line px-3 py-3 text-sm {{ $locked ? 'opacity-70' : 'cursor-pointer has-[:checked]:border-brand has-[:checked]:bg-brand-soft' }}">
                            <input type="checkbox" class="check mt-0.5" name="modules[]" value="{{ $key }}" @checked($tenant->hasModule($key) || $core) @disabled($core || $locked)>
                            <span>
                                <span class="font-medium">{{ __($label) }}</span>
                                @if ($core)<span class="block text-muted">{{ __('Always on') }}</span>@endif
                                @if ($locked)<span class="block text-muted">{{ __('Not included in your plan') }}</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-card>
            @can('settings.update')<div class="mt-6 flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save modules') }}</button></div>@endcan
        </form>
    @endcomponent
@endsection
