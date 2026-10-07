<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Project;
use App\Models\SubscriptionInvoice;
use App\Services\FeatureService;
use App\Services\SubscriptionService;
use App\Services\UsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The workspace's own SaaS subscription: plan, usage, billing history, upgrade, downgrade, cancel. */
class BillingController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function index(FeatureService $features, UsageService $usage)
    {
        $tenant = tenant();

        return view('settings.billing', [
            'tenant' => $tenant,
            'subscription' => $this->subscriptions->current($tenant)?->load('plan'),
            'plans' => Plan::with('features')->where('is_active', true)->where('is_public', true)->orderBy('sort_order')->get(),
            'history' => SubscriptionInvoice::latest()->limit(24)->get(),
            'gateway' => $this->subscriptions->gatewayEnabled(),
            'usage' => [
                ['label' => __('Team members'), 'used' => $tenant->users()->count(), 'limit' => $features->limit('max_users')],
                ['label' => __('Customers'), 'used' => Customer::count(), 'limit' => $features->limit('max_customers')],
                ['label' => __('Invoices this month'), 'used' => Invoice::where('created_at', '>=', now()->startOfMonth())->count(), 'limit' => $features->limit('max_invoices')],
                ['label' => __('Products & services'), 'used' => Product::count(), 'limit' => $features->limit('max_products')],
                ['label' => __('Projects'), 'used' => Project::count(), 'limit' => $features->limit('max_projects')],
                ['label' => __('Storage (MB)'), 'used' => round($usage->total('storage_bytes') / 1048576, 1), 'limit' => $features->limit('max_storage')],
                ['label' => __('Emails sent this month'), 'used' => $usage->current('emails_sent'), 'limit' => null],
                ['label' => __('API calls this month'), 'used' => $usage->current('api_calls'), 'limit' => null],
            ],
        ]);
    }

    public function change(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'coupon' => ['nullable', 'string', 'max:50'],
        ]);

        $tenant = tenant();
        $plan = Plan::findOrFail($data['plan_id']);
        $coupon = $this->subscriptions->findCoupon($data['coupon'] ?? null, $plan, $tenant);
        $price = (float) $this->subscriptions->price($plan, $data['cycle'], $coupon);

        // Paid plan with card billing configured: the customer pays on Stripe and the webhook activates the plan
        if ($price > 0 && $this->subscriptions->gatewayEnabled()) {
            return redirect()->away($this->subscriptions->checkoutUrl($tenant, $plan, $data['cycle'], $coupon));
        }

        // Free plan, or manual billing mode: the plan changes now and an invoice is recorded for the platform team
        $this->subscriptions->changePlan($tenant, $plan, $data['cycle'], $coupon);

        return back()->with('success', __('Your plan is now :plan.', ['plan' => $plan->name]));
    }

    public function cancel(): RedirectResponse
    {
        $subscription = $this->subscriptions->cancel(tenant());

        return back()->with('success', __('Subscription cancelled. You keep access until :date.', ['date' => tdate($subscription?->ends_at)]));
    }

    public function resume(): RedirectResponse
    {
        $this->subscriptions->resume(tenant());

        return back()->with('success', __('Subscription resumed.'));
    }
}
