<?php

namespace App\Http\Controllers;

use App\Models\BusinessSignal;
use App\Models\Product;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Services\BusinessHealthService;
use App\Services\DashboardService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard, private BusinessHealthService $health) {}

    public function index(Request $request)
    {
        $period = $request->query('period', '30d');
        [$from, $to] = DashboardService::range($period, $request->query('from'), $request->query('to'));

        // Widgets and their order: the user's own arrangement, or the preset of the business type
        $defaults = config('saas.business_types.'.(tenant()->business_type ?: 'other').'.widgets') ?? config('saas.business_types.other.widgets');
        $widgets = array_values(array_filter($request->user()->dashboard_config[tenant()->id]['widgets'] ?? $defaults, fn ($widget) => $this->widgetAvailable($widget)));

        $signals = feature('business_health') && $request->user()->hasPermission('health.view') ? $this->health->current() : collect();

        return view('dashboard.index', [
            'period' => $period, 'from' => $from, 'to' => $to,
            'summary' => $this->dashboard->summary($from, $to),
            'series' => $this->dashboard->series($from, $to),
            'expenseCategories' => $this->dashboard->expensesByCategory($from, $to),
            'statusCounts' => $this->dashboard->invoiceStatusCounts(),
            'transactions' => $this->dashboard->recentTransactions(),
            'activity' => $this->dashboard->recentActivity(),
            'actions' => $this->health->actionCenter(),
            'signals' => $signals->take(4),
            'brief' => feature('business_health') ? $this->health->brief('monthly') : null,
            'widgets' => $widgets,
            'allWidgets' => array_filter(config('saas.widgets'), fn ($label, $key) => $this->widgetAvailable($key), ARRAY_FILTER_USE_BOTH),
            'extra' => $this->extras($widgets),
        ]);
    }

    /** Save the user's widget selection and order (per workspace). */
    public function saveWidgets(Request $request): RedirectResponse
    {
        $data = $request->validate(['widgets' => ['required', 'array', 'min:1'], 'widgets.*' => ['string', 'in:'.implode(',', array_keys(config('saas.widgets')))]]);
        $config = $request->user()->dashboard_config ?? [];
        $config[tenant()->id] = ['widgets' => array_values(array_unique($data['widgets']))];
        $request->user()->forceFill(['dashboard_config' => $config])->save();

        return back()->with('success', __('Dashboard saved.'));
    }

    private function widgetAvailable(string $widget): bool
    {
        return match ($widget) {
            'inventory' => feature('inventory'),
            'projects', 'time' => feature('projects'),
            'signals' => feature('business_health'),
            'expenses' => feature('expenses'),
            default => array_key_exists($widget, config('saas.widgets')),
        };
    }

    private function extras(array $widgets): array
    {
        $extra = [];

        if (in_array('inventory', $widgets, true)) {
            $extra['inventory'] = ['low' => Product::lowStock()->count(), 'value' => app(InventoryService::class)->valuation()];
        }

        if (in_array('projects', $widgets, true)) {
            $extra['projects'] = ['active' => Project::where('status', 'active')->count(), 'due_soon' => Project::where('status', 'active')->whereNotNull('deadline')->whereDate('deadline', '<=', today_local()->addDays(7)->toDateString())->count()];
        }

        if (in_array('time', $widgets, true)) {
            $extra['time'] = [
                'week' => (int) TimeEntry::whereDate('entry_date', '>=', today_local()->startOfWeek()->toDateString())->sum('duration_seconds'),
                'unbilled' => (int) TimeEntry::where('is_billable', true)->whereNull('invoice_id')->sum('duration_seconds'),
            ];
        }

        return $extra;
    }

    /* ------------------------------ Business Health ------------------------------ */

    public function health()
    {
        $signals = $this->health->current();

        // First visit: detect signals right away instead of waiting for the nightly job
        if ($signals->isEmpty() && ! BusinessSignal::exists()) {
            $signals = $this->health->generate();
        }

        $today = today_local();

        return view('health.index', [
            'score' => $this->health->score(),
            'signals' => $signals->groupBy('type'),
            'actions' => $this->health->actionCenter(),
            'brief' => $this->health->brief('monthly'),
            'forecast' => app(\App\Services\CashFlowService::class)->forecast(),
            'trend' => $this->dashboard->series($today->copy()->subMonthsNoOverflow(5)->startOfMonth(), $today),
            'lastRun' => BusinessSignal::max('created_at'),
        ]);
    }

    public function refreshHealth(): RedirectResponse
    {
        DashboardService::flush(tenant()->id);
        $this->health->generate();

        return back()->with('success', __('Business signals updated.'));
    }

    public function dismissSignal(BusinessSignal $signal): RedirectResponse
    {
        $signal->update(['dismissed_at' => now()]);

        return back()->with('success', __('Signal dismissed.'));
    }
}
