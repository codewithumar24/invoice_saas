<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Time tracking with a Start / Pause / Stop timer and manual entries. */
class TimeEntryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $seeAll = $user->hasPermission('projects.manage');

        $query = TimeEntry::with(['project', 'task', 'user'])
            ->when(! $seeAll, fn ($q) => $q->where('user_id', $user->id))
            ->when($seeAll && $request->query('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->when($request->query('project_id'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->query('billable') !== null && $request->query('billable') !== '', fn ($q) => $q->where('is_billable', $request->query('billable') === '1'))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('entry_date', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('entry_date', '<=', $date));

        return view('time.index', [
            'totalSeconds' => (int) (clone $query)->sum('duration_seconds'),
            'entries' => $query->latest('entry_date')->latest('id')->paginate(20)->withQueryString(),
            'active' => TimeEntry::with(['project', 'task'])->where('user_id', $user->id)->whereIn('status', ['running', 'paused'])->latest('id')->first(),
            'projects' => Project::with('tasks:id,project_id,title')->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->get(['id', 'name', 'hourly_rate']),
            'members' => $seeAll ? tenant()->users()->orderBy('name')->get() : collect(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (TimeEntry::where('user_id', $request->user()->id)->whereIn('status', ['running', 'paused'])->exists()) {
            throw ValidationException::withMessages(['project_id' => __('Stop your current timer before starting a new one.')]);
        }

        TimeEntry::create($data + [
            'user_id' => $request->user()->id, 'entry_date' => today_local(), 'started_at' => now(), 'status' => 'running',
            'hourly_rate' => $data['hourly_rate'] ?? Project::find($data['project_id'] ?? null)?->hourly_rate ?? 0,
        ]);

        return back()->with('success', __('Timer started.'));
    }

    public function pause(Request $request, TimeEntry $entry): RedirectResponse
    {
        $this->own($request, $entry);

        if ($entry->status === 'running') {
            $entry->update(['duration_seconds' => $entry->elapsedSeconds(), 'started_at' => null, 'status' => 'paused']);
        }

        return back()->with('success', __('Timer paused.'));
    }

    public function resume(Request $request, TimeEntry $entry): RedirectResponse
    {
        $this->own($request, $entry);

        if ($entry->status === 'paused') {
            $entry->update(['started_at' => now(), 'status' => 'running']);
        }

        return back()->with('success', __('Timer resumed.'));
    }

    public function stop(Request $request, TimeEntry $entry): RedirectResponse
    {
        $this->own($request, $entry);

        if ($entry->status !== 'stopped') {
            $entry->update(['duration_seconds' => $entry->elapsedSeconds(), 'started_at' => null, 'status' => 'stopped']);
        }

        return back()->with('success', __('Timer stopped. :time recorded.', ['time' => TimeEntry::formatDuration($entry->duration_seconds)]));
    }

    /** Manual entry: hours typed in after the fact. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request) + $request->validate([
            'entry_date' => ['required', 'date', 'before_or_equal:tomorrow'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:24'],
        ]);

        TimeEntry::create(collect($data)->except('hours')->all() + [
            'user_id' => $request->user()->id, 'status' => 'stopped', 'duration_seconds' => (int) round($data['hours'] * 3600),
            'hourly_rate' => $data['hourly_rate'] ?? Project::find($data['project_id'] ?? null)?->hourly_rate ?? 0,
        ]);

        return back()->with('success', __('Time entry added.'));
    }

    public function destroy(Request $request, TimeEntry $entry): RedirectResponse
    {
        $this->own($request, $entry);

        if ($entry->invoice_id) {
            return back()->with('error', __('This time was already invoiced and cannot be deleted.'));
        }

        $entry->delete();

        return back()->with('success', __('Time entry deleted.'));
    }

    private function own(Request $request, TimeEntry $entry): void
    {
        abort_unless($entry->user_id === $request->user()->id || $request->user()->hasPermission('projects.manage'), 403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', tenant_exists('projects')],
            'project_task_id' => ['nullable', 'integer', tenant_exists('project_tasks')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['is_billable'] = $request->boolean('is_billable');

        return array_filter($data, fn ($value) => $value !== null);
    }
}
