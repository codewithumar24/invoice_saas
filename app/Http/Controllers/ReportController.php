<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\InventoryLocation;
use App\Models\ProductCategory;
use App\Services\DashboardService;
use App\Services\ReportService;
use App\Services\UsageService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $reports = collect(ReportService::REPORTS)->filter(fn ($report, $key) => $this->available($key))
            ->map(fn ($report, $key) => ['key' => $key, 'title' => __($report[0]), 'group' => $report[1], 'locked' => $report[2] && ! feature('advanced_reports')])
            ->groupBy('group');

        return view('reports.index', ['groups' => $reports]);
    }

    public function show(Request $request, string $report, ReportService $reports, UsageService $usage)
    {
        abort_unless(isset(ReportService::REPORTS[$report]) && $this->available($report), 404);

        if (ReportService::REPORTS[$report][2] && ! feature('advanced_reports')) {
            return redirect()->route('reports.index')->with('error', __('This report is part of advanced reports. Upgrade your plan to open it.'));
        }

        $period = $request->query('period', '30d');
        [$from, $to] = DashboardService::range($period, $request->query('from'), $request->query('to'));
        $filters = $request->only(['customer_id', 'category_id', 'location_id', 'currency', 'branch_id']);
        $usage->increment('reports_generated');

        return view('reports.show', [
            'report' => $reports->run($report, $from, $to, $filters),
            'period' => $period, 'filters' => $filters,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(['id', 'name']),
            'productCategories' => ProductCategory::orderBy('name')->get(['id', 'name']),
            'locations' => feature('inventory') ? InventoryLocation::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /** Hide reports of modules the workspace does not use. */
    private function available(string $key): bool
    {
        return match (ReportService::REPORTS[$key][1]) {
            'Inventory' => feature('inventory'),
            'Expense' => feature('expenses'),
            default => true,
        };
    }
}
