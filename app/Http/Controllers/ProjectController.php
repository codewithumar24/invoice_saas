<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\FeatureService;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Project::class);

        $query = Project::with('customer')->withCount(['tasks', 'tasks as open_tasks_count' => fn ($q) => $q->where('status', '!=', 'done')])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'archived'))
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', $this->like($term)));

        return view('projects.index', [
            'projects' => $this->sorted($query, $request, ['name', 'deadline', 'budget', 'created_at'])->paginate($this->perPage)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Project::class);

        return view('projects.form', ['project' => new Project(['status' => 'active', 'customer_id' => $request->query('customer_id'), 'start_date' => today_local()]), 'customers' => Customer::active()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, FeatureService $features): RedirectResponse
    {
        Gate::authorize('create', Project::class);
        $features->enforceLimit('max_projects', Project::count(), __('projects'));
        $project = Project::create($this->validated($request));

        return redirect()->route('projects.show', $project)->with('success', __('Project created.'));
    }

    public function show(Project $project)
    {
        Gate::authorize('view', $project);
        $project->load(['customer', 'tasks.assignee', 'invoices']);
        $entries = $project->timeEntries()->with(['user', 'task'])->latest('entry_date')->latest('id')->limit(50)->get();
        $invoiced = $project->invoices->whereNotIn('status', ['draft', 'cancelled'])->sum(fn ($i) => (float) $i->total * (float) $i->exchange_rate);
        $spent = (float) $project->expenses()->selectRaw('COALESCE(SUM(amount * exchange_rate), 0) as value')->value('value');

        return view('projects.show', [
            'project' => $project,
            'entries' => $entries,
            'members' => tenant()->users()->wherePivot('status', 'active')->orderBy('name')->get(),
            'stats' => [
                'seconds' => (int) $project->timeEntries()->sum('duration_seconds'),
                'unbilled_seconds' => (int) $project->timeEntries()->where('is_billable', true)->whereNull('invoice_id')->sum('duration_seconds'),
                'invoiced' => $invoiced, 'expenses' => $spent, 'profit' => $invoiced - $spent,
            ],
        ]);
    }

    public function edit(Project $project)
    {
        Gate::authorize('update', $project);

        return view('projects.form', ['project' => $project, 'customers' => Customer::orderBy('name')->get(['id', 'name'])]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        $project->update($this->validated($request));

        return redirect()->route('projects.show', $project)->with('success', __('Project saved.'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        // Projects with billing history are archived rather than deleted
        if ($project->invoices()->exists() || $project->timeEntries()->exists()) {
            $project->update(['status' => 'archived']);

            return redirect()->route('projects.index')->with('success', __('This project has history, so it was archived.'));
        }

        $project->delete();

        return redirect()->route('projects.index')->with('success', __('Project deleted.'));
    }

    /** Convert billable time into a draft invoice. */
    public function invoice(Project $project, InvoiceService $invoices): RedirectResponse
    {
        Gate::authorize('invoices.create');
        Gate::authorize('view', $project);
        $invoice = $invoices->createFromTimeEntries($project);

        return redirect()->route('invoices.edit', $invoice)->with('success', __('Draft invoice :number created from billable time.', ['number' => $invoice->number]));
    }

    public function storeTask(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        $project->tasks()->create($this->validatedTask($request));

        return back()->with('success', __('Task added.'));
    }

    public function updateTask(Request $request, ProjectTask $task): RedirectResponse
    {
        Gate::authorize('update', $task->project);

        // The quick status buttons send only a status
        $task->update($request->has('title') ? $this->validatedTask($request) : $request->validate(['status' => ['required', Rule::in(['todo', 'in_progress', 'done'])]]));

        return back()->with('success', __('Task updated.'));
    }

    public function destroyTask(ProjectTask $task): RedirectResponse
    {
        Gate::authorize('update', $task->project);
        $task->delete();

        return back()->with('success', __('Task deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', tenant_exists('customers')],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(Project::STATUSES)],
        ]);

        return ['budget' => $data['budget'] ?? 0, 'hourly_rate' => $data['hourly_rate'] ?? 0] + $data;
    }

    private function validatedTask(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('tenant_user', 'user_id')->where('tenant_id', tenant()->id)],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'status' => ['required', Rule::in(['todo', 'in_progress', 'done'])],
        ]);
    }
}
