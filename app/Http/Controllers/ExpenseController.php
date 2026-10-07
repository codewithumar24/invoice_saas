<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Services\CurrencyService;
use App\Services\DashboardService;
use App\Services\ExpenseService;
use App\Services\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Expense::class);

        $query = Expense::with(['category', 'receipts'])
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('vendor', 'like', $this->like($term))
                ->orWhere('reference', 'like', $this->like($term))->orWhere('notes', 'like', $this->like($term))))
            ->when($request->query('category'), fn ($q, $category) => $category === 'none' ? $q->whereNull('category_id') : $q->where('category_id', $category))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('expense_date', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('expense_date', '<=', $date))
            ->when($request->query('min'), fn ($q, $min) => $q->where('amount', '>=', (float) $min))
            ->when($request->query('max'), fn ($q, $max) => $q->where('amount', '<=', (float) $max));

        return view('expenses.index', [
            'total' => (float) (clone $query)->selectRaw('COALESCE(SUM(amount * exchange_rate), 0) as value')->value('value'),
            'expenses' => $this->sorted($query, $request, ['expense_date', 'amount', 'vendor'], 'expense_date')->paginate($this->perPage)->withQueryString(),
            'categories' => ExpenseCategory::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        Gate::authorize('create', Expense::class);

        return view('expenses.form', ['expense' => new Expense(['expense_date' => today_local(), 'currency' => tenant()->currency])] + $this->formData());
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Gate::authorize('create', Expense::class);
        $this->expenses->create($request->validated(), $request->file('receipts', []));

        return redirect()->route('expenses.index')->with('success', __('Expense recorded.'));
    }

    public function edit(Expense $expense)
    {
        Gate::authorize('update', $expense);
        $expense->load(['receipts', 'tags']);

        return view('expenses.form', ['expense' => $expense] + $this->formData());
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);
        $this->expenses->update($expense, $request->validated(), $request->file('receipts', []));
        DashboardService::flush($expense->tenant_id);

        return redirect()->route('expenses.index')->with('success', __('Expense saved.'));
    }

    /** Soft delete: the record leaves the lists but stays in the database and the audit log. */
    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);
        $expense->delete();
        DashboardService::flush($expense->tenant_id);

        return redirect()->route('expenses.index')->with('success', __('Expense deleted.'));
    }

    /** Every download is authorised; receipts are never publicly reachable. */
    public function receipt(ExpenseAttachment $receipt, FileStorageService $files)
    {
        Gate::authorize('view', $receipt->expense);

        return $files->download($receipt->disk, $receipt->path, $receipt->original_name);
    }

    public function destroyReceipt(ExpenseAttachment $receipt, FileStorageService $files): RedirectResponse
    {
        Gate::authorize('update', $receipt->expense);
        $files->delete($receipt->disk, $receipt->path);
        $receipt->delete();

        return back()->with('success', __('Receipt removed.'));
    }

    public function storeCategory(Request $request)
    {
        Gate::authorize('expenses.create');
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $category = ExpenseCategory::firstOrCreate(['name' => trim($data['name'])]);

        return $request->expectsJson() ? $this->ok($category->only(['id', 'name']), __('Category added.'), 201) : back()->with('success', __('Category added.'));
    }

    public function destroyCategory(ExpenseCategory $category): RedirectResponse
    {
        Gate::authorize('expenses.delete');
        $category->delete(); // expenses keep their data; their category becomes empty

        return back()->with('success', __('Category deleted.'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:categorize,export'],
            'ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer'],
            'category_id' => ['nullable', 'required_if:action,categorize', 'integer', tenant_exists('expense_categories')],
        ]);

        if ($data['action'] === 'export') {
            Gate::authorize('reports.export');

            return redirect()->route('exports.download-now', ['type' => 'expenses', 'format' => 'csv', 'ids' => $data['ids']]);
        }

        Gate::authorize('expenses.update');
        $count = 0;

        // Updated one by one so each change is written to the audit log
        foreach (Expense::whereIn('id', $data['ids'])->get() as $expense) {
            $expense->update(['category_id' => $data['category_id']]);
            $count++;
        }

        DashboardService::flush(tenant()->id);

        return back()->with('success', trans_choice(':count expense categorised.|:count expenses categorised.', $count, ['count' => $count]));
    }

    private function formData(): array
    {
        return [
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'projects' => feature('projects') ? Project::orderBy('name')->get(['id', 'name']) : collect(),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'currencies' => app(CurrencyService::class)->options(),
        ];
    }
}
