{{-- Period filter shared by the dashboard and reports. --}}
@php $periods = ['today' => __('Today'), '7d' => __('7 days'), '30d' => __('30 days'), '3m' => __('3 months'), '6m' => __('6 months'), '12m' => __('12 months'), 'custom' => __('Custom')]; @endphp
<form method="GET" class="flex flex-wrap items-end gap-2" data-no-loading>
    @foreach (request()->except(['period', 'from', 'to', 'page']) as $key => $value)
        @if (is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
    @endforeach
    <div>
        <label for="period" class="sr-only">{{ __('Period') }}</label>
        <select name="period" id="period" class="input w-auto" data-autosubmit>
            @foreach ($periods as $value => $label)
                <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    @if ($period === 'custom')
        <div><label for="from" class="sr-only">{{ __('From') }}</label><input type="date" name="from" id="from" value="{{ $from->toDateString() }}" class="input w-auto"></div>
        <div><label for="to" class="sr-only">{{ __('To') }}</label><input type="date" name="to" id="to" value="{{ $to->toDateString() }}" class="input w-auto"></div>
        <button type="submit" class="btn btn-secondary">{{ __('Apply') }}</button>
    @endif
</form>
