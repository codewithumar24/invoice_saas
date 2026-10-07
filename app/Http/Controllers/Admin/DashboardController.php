<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\ContactMessage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

/** Platform dashboard, system logs, queue monitor, backups and platform settings. */
class DashboardController extends Controller
{
    public function index(SubscriptionService $subscriptions)
    {
        $subs = Subscription::withoutGlobalScope('tenant');
        $mrr = $subscriptions->mrr();
        $activeStart = (clone $subs)->where('created_at', '<', now()->subDays(30))->whereIn('status', ['active', 'cancelled', 'expired'])->count();
        $churned = (clone $subs)->whereIn('status', ['cancelled', 'expired'])->where('cancelled_at', '>=', now()->subDays(30))->count();

        // Twelve monthly buckets for the trend charts
        $months = collect(range(11, 0))->mapWithKeys(fn ($back) => [now()->subMonthsNoOverflow($back)->format('Y-m') => 0]);
        $byMonth = fn ($rows, string $column) => $months->merge($rows->groupBy(fn ($row) => \Illuminate\Support\Carbon::parse($row->{$column})->format('Y-m'))->map->count()->only($months->keys()))->values()->all();
        $since = now()->subMonthsNoOverflow(11)->startOfMonth();

        $revenue = $months->merge(
    SubscriptionInvoice::withoutGlobalScope('tenant')
        ->where('status', 'paid')
        ->where('paid_at', '>=', $since)
        ->get()
        ->toBase()
        ->groupBy(fn ($invoice) => $invoice->paid_at->format('Y-m'))
        ->map(fn ($group) => round($group->sum(fn ($i) => (float) $i->amount), 2))
        ->only($months->keys()->all())
)->values()->all();

        return view('admin.dashboard', [
            'metrics' => [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'new_registrations' => Tenant::where('created_at', '>=', now()->subDays(30))->count(),
                'mrr' => $mrr, 'arr' => $mrr * 12,
                'churn' => $activeStart > 0 ? round($churned / $activeStart * 100, 1) : 0,
                'trial_users' => (clone $subs)->where('status', 'trial')->count(),
                'paid_users' => (clone $subs)->where('status', 'active')->where('amount', '>', 0)->count(),
                'failed_payments' => (clone $subs)->where('status', 'past_due')->count() + SubscriptionInvoice::withoutGlobalScope('tenant')->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'charts' => [
                'labels' => $months->keys()->map(fn ($month) => \Illuminate\Support\Carbon::parse($month.'-01')->format('M y'))->all(),
                'registrations' => $byMonth(Tenant::where('created_at', '>=', $since)->get(['created_at']), 'created_at'),
                'revenue' => $revenue,
                'subscriptions' => $byMonth((clone $subs)->where('created_at', '>=', $since)->where('amount', '>', 0)->get(['created_at']), 'created_at'),
                'churn' => $byMonth((clone $subs)->whereNotNull('cancelled_at')->where('cancelled_at', '>=', $since)->get(['cancelled_at']), 'cancelled_at'),
            ],
            'recentTenants' => Tenant::with('plan')->latest()->limit(8)->get(),
            'health' => $this->systemHealth(),
        ]);
    }

    private function systemHealth(): array
    {
        $lastBackup = BackupLog::latest()->first();
        $lastScheduler = Cache::get('scheduler:last_run');

        return [
            'queue_driver' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'pending_jobs' => $this->tableCount('jobs'),
            'failed_jobs' => $this->tableCount('failed_jobs'),
            'scheduler' => $lastScheduler ? \Illuminate\Support\Carbon::parse($lastScheduler) : null,
            'last_backup' => $lastBackup,
            'debug' => (bool) config('app.debug'),
        ];
    }

    private function tableCount(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (\Throwable) {
            return null;
        }
    }

    /* ------------------------------ Logs ------------------------------ */

    public function logs(Request $request)
    {
        $audit = AuditLog::with(['user', 'tenant'])
            ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', $this->like($action)))
            ->when($request->query('tenant_id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->orderByDesc('id')->paginate(40)->withQueryString();

        return view('admin.logs', ['audit' => $audit, 'lines' => $request->query('tab') === 'system' ? $this->tailLog(200) : []]);
    }

    /** Last lines of the application log. Shown to platform administrators only. */
    private function tailLog(int $lines): array
    {
        $path = storage_path('logs/laravel.log');

        if (! File::exists($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        fseek($handle, max(0, filesize($path) - 200000));
        $content = stream_get_contents($handle);
        fclose($handle);

        return array_slice(explode("\n", trim((string) $content)), -$lines);
    }

    /* ------------------------------ Queue monitor ------------------------------ */

    public function queue()
    {
        $pending = collect();
        $failed = collect();

        try {
            $pending = DB::table('jobs')->selectRaw('queue, COUNT(*) as total, MIN(created_at) as oldest')->groupBy('queue')->get();
            $failed = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(50)->get();
        } catch (\Throwable) {
            // Redis queues have no jobs table; Horizon is the monitor in that case
        }

        return view('admin.queue', [
            'pending' => $pending, 'failed' => $failed, 'driver' => config('queue.default'),
            'horizon' => class_exists(\Laravel\Horizon\Horizon::class),
            'scheduler' => Cache::get('scheduler:last_run'),
        ]);
    }

    public function retryJob(string $id): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => [$id]]);

        return back()->with('success', __('Job sent back to the queue.'));
    }

    public function forgetJob(string $id): RedirectResponse
    {
        Artisan::call('queue:forget', ['id' => $id]);

        return back()->with('success', __('Failed job removed.'));
    }

    /* ------------------------------ Backups ------------------------------ */

    public function backups()
    {
        return view('admin.backups', ['backups' => BackupLog::latest()->paginate(30), 'enabled' => config('saas.backup.enabled'), 'retention' => config('saas.backup.retention_days')]);
    }

    public function runBackup(): RedirectResponse
    {
        $exit = Artisan::call('saas:backup');
        app(AuditService::class)->log('admin.backup_run');

        return back()->with($exit === 0 ? 'success' : 'error', $exit === 0 ? __('Backup created.') : __('Backup failed. See the backup list for the reason.'));
    }

    /* ------------------------------ Platform settings ------------------------------ */

    public function settings()
    {
        return view('admin.settings', ['plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function updateSettings(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'default_currency' => ['required', Rule::in(array_keys(config('currencies')))],
            'default_plan' => ['required', Rule::exists('plans', 'slug')],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'billing_gateway' => ['required', Rule::in(['manual', 'stripe'])],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
            'admin_ip_allowlist' => ['nullable', 'string', 'max:1000'],
            'analytics_snippet_id' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]*$/'],
        ]);

        $settings->setPlatform(array_map(fn ($value) => $value ?? '', $data) + [
            'registration_enabled' => $request->boolean('registration_enabled'),
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'admin_require_2fa' => $request->boolean('admin_require_2fa'),
        ]);

        app(AuditService::class)->log('admin.settings_updated', null, ['maintenance_mode' => $request->boolean('maintenance_mode')]);

        return back()->with('success', __('Platform settings saved.'));
    }

    public function messages()
    {
        return view('admin.messages', ['messages' => ContactMessage::latest()->paginate(30)]);
    }

    /* ------------------------------ Users ------------------------------ */

    public function users(Request $request)
    {
        $users = User::withCount('tenants')
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', $this->like($term))->orWhere('email', 'like', $this->like($term))))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.users', ['users' => $users]);
    }

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, __('You cannot suspend yourself.'));
        $user->forceFill(['status' => $user->status === 'active' ? 'suspended' : 'active'])->save();

        if ($user->status === 'suspended') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        app(AuditService::class)->log('admin.user_'.$user->status, $user->email);

        return back()->with('success', $user->status === 'active' ? __('User reactivated.') : __('User suspended and signed out.'));
    }
}
