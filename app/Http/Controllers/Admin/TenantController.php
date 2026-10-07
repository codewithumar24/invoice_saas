<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $tenants = Tenant::with('plan')->withCount('users')
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', $this->like($term))->orWhere('email', 'like', $this->like($term))->orWhere('slug', 'like', $this->like($term))))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('plan_id'), fn ($q, $plan) => $q->where('plan_id', $plan))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.tenants.index', ['tenants' => $tenants, 'plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function show(Tenant $tenant, SubscriptionService $subscriptions)
    {
        return view('admin.tenants.show', [
            'tenant' => $tenant->load('plan'),
            'members' => $tenant->users()->get(),
            'subscription' => $subscriptions->current($tenant)?->load('plan'),
            'billing' => SubscriptionInvoice::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->latest()->limit(20)->get(),
            'usage' => UsageRecord::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('period', now()->format('Y-m'))->pluck('quantity', 'metric'),
            'counts' => [
                'customers' => DB::table('customers')->where('tenant_id', $tenant->id)->count(),
                'invoices' => DB::table('invoices')->where('tenant_id', $tenant->id)->count(),
                'products' => DB::table('products')->where('tenant_id', $tenant->id)->count(),
            ],
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function toggle(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => $tenant->status === 'active' ? 'suspended' : 'active']);
        app(AuditService::class)->log('admin.tenant_'.$tenant->status, $tenant->name, [], $tenant->id);

        return back()->with('success', $tenant->status === 'active' ? __('Workspace activated.') : __('Workspace suspended.'));
    }

    public function changePlan(Request $request, Tenant $tenant, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id'], 'cycle' => ['required', Rule::in(['monthly', 'yearly'])]]);
        $plan = Plan::findOrFail($data['plan_id']);
        $subscriptions->changePlan($tenant, $plan, $data['cycle']);
        app(AuditService::class)->log('admin.tenant_plan_changed', $tenant->name, ['plan' => $plan->slug], $tenant->id);

        return back()->with('success', __('Plan changed to :plan.', ['plan' => $plan->name]));
    }

    /**
     * Sign in as the workspace owner to help with a support request.
     * Every impersonation is written to the audit log, and every audit entry made while impersonating carries the admin's id.
     */
    public function impersonate(Request $request, Tenant $tenant): RedirectResponse
    {
        $owner = $tenant->owner();
        abort_unless($owner, 404);
        abort_if($owner->is_super_admin, 403);

        $admin = $request->user();
        app(AuditService::class)->log('admin.impersonation_started', $owner->email, ['tenant' => $tenant->name], $tenant->id);

        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $admin->id);
        $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

        return redirect()->route('dashboard')->with('success', __('You are now signed in as :name.', ['name' => $owner->name]));
    }

    /** Reached while signed in as the impersonated user, so it lives outside the super-admin middleware. */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');
        $admin = $adminId ? User::where('is_super_admin', true)->find($adminId) : null;
        abort_unless($admin, 403);

        app(AuditService::class)->log('admin.impersonation_stopped', $request->user()->email);
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.tenants.index')->with('success', __('Impersonation ended.'));
    }

    public function subscriptions(Request $request)
    {
        $subscriptions = \App\Models\Subscription::withoutGlobalScope('tenant')->with(['plan', 'tenant'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.subscriptions', ['subscriptions' => $subscriptions]);
    }

    public function payments(Request $request)
    {
        $invoices = SubscriptionInvoice::withoutGlobalScope('tenant')->with('tenant')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.payments', ['invoices' => $invoices]);
    }

    /** Manual billing mode: the platform team confirms a bank transfer or other offline payment. */
    public function markPaid(int $invoice): RedirectResponse
    {
        $invoice = SubscriptionInvoice::withoutGlobalScope('tenant')->findOrFail($invoice);
        $invoice->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
        app(AuditService::class)->log('admin.subscription_invoice_paid', $invoice->number, [], $invoice->tenant_id);

        return back()->with('success', __('Invoice marked as paid.'));
    }
}
