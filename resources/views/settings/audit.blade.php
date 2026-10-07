@extends('layouts.app')
@section('title', __('Audit log'))

@section('content')
    @component('settings._layout')
        <div>
            <p class="mb-4 text-sm text-muted">{{ __('A permanent record of sensitive changes: who did what, when and from where. Entries cannot be edited or deleted.') }}</p>

            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3" data-no-loading>
                <div class="w-full sm:w-52"><label for="f-action" class="sr-only">{{ __('Action') }}</label><input type="search" name="action" id="f-action" value="{{ request('action') }}" class="input" placeholder="{{ __('Action, for example updated') }}"></div>
                <div>
                    <label for="f-entity" class="sr-only">{{ __('Record type') }}</label>
                    <select name="entity" id="f-entity" class="input w-auto">
                        <option value="">{{ __('All records') }}</option>
                        @foreach ($entities as $entity)<option value="{{ $entity }}" @selected(request('entity') === $entity)>{{ __($entity) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="f-user" class="sr-only">{{ __('Team member') }}</label>
                    <select name="user_id" id="f-user" class="input w-auto">
                        <option value="">{{ __('Everyone') }}</option>
                        @foreach ($members as $member)<option value="{{ $member->id }}" @selected((string) request('user_id') === (string) $member->id)>{{ $member->name }}</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
            </form>

            @if ($logs->count())
                <x-table>
                    <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('What') }}</th><th>{{ __('Changes') }}</th><th>{{ __('IP address') }}</th></tr></thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td data-label="{{ __('When') }}" class="whitespace-nowrap">{{ tdate($log->created_at, 'd M Y, H:i') }}</td>
                                <td data-label="{{ __('Who') }}">{{ $log->user?->name ?? __('System') }}@if ($log->impersonator_id)<span class="badge badge-warn ms-1">{{ __('via support') }}</span>@endif</td>
                                <td data-label="{{ __('What') }}">{{ status_label(str_replace('.', ' ', $log->action)) }}@if ($log->entity_type) <span class="text-muted">{{ __($log->entity_type) }}</span>@endif @if ($log->entity_label)<span class="font-medium">{{ $log->entity_label }}</span>@endif</td>
                                <td data-label="{{ __('Changes') }}" class="max-w-sm">
                                    @if ($log->action === 'updated' && $log->new_values)
                                        <ul class="space-y-0.5 text-xs">
                                            @foreach (array_slice($log->new_values, 0, 6, true) as $field => $value)
                                                <li><span class="text-muted">{{ str_replace('_', ' ', $field) }}:</span> <span class="num">{{ \Illuminate\Support\Str::limit(is_scalar($log->old_values[$field] ?? null) ? (string) $log->old_values[$field] : '—', 40) }}</span> &rarr; <span class="num font-medium">{{ \Illuminate\Support\Str::limit(is_scalar($value) ? (string) $value : json_encode($value), 40) }}</span></li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="{{ __('IP address') }}" class="num text-muted">{{ $log->ip ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
                {{ $logs->links() }}
            @else
                <div class="card"><x-empty :title="__('No entries match')" /></div>
            @endif
        </div>
    @endcomponent
@endsection
