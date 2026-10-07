<?php

namespace App\Services;

use App\Events\SubscriptionChanged;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** SaaS billing: plans, trials, upgrades, downgrades, cancellations and renewals for workspaces. */
class SubscriptionService
{
    public function __construct(private SettingsService $settings) {}

    public function defaultPlan(): ?Plan
    {
        return Plan::where('slug', $this->settings->platform('default_plan', 'free'))->first() ?? Plan::orderBy('price_monthly')->first();
    }

    private function query()
    {
        return Subscription::withoutGlobalScope('tenant');
    }

    public function current(Tenant $tenant): ?Subscription
    {
        return $this->query()->where('tenant_id', $tenant->id)->latest('id')->first();
    }

    /**
     * First subscription of a new workspace. When the platform offers a trial (trial_days > 0),
     * the workspace starts on the trial plan and falls back to the default plan when the trial ends.
     */
    public function startFor(Tenant $tenant, ?Plan $plan = null): ?Subscription
    {
        $trialDays = (int) $this->settings->platform('trial_days', 14);
        $trialPlan = $plan ? null : Plan::where('slug', $this->settings->platform('trial_plan', 'professional'))->where('is_active', true)->first();

        if ($trialPlan && $trialDays > 0) {
            return $this->open($tenant, $trialPlan, 'monthly', 'trial', $trialDays);
        }

        $plan ??= $this->defaultPlan();

        return $plan ? $this->open($tenant, $plan, 'monthly', 'active') : null;
    }

    private function open(Tenant $tenant, Plan $plan, string $cycle, string $status, int $trialDays = 0, ?Coupon $coupon = null, ?string $gateway = null, ?string $gatewayId = null): Subscription
    {
        $subscription = new Subscription([
            'plan_id' => $plan->id,
            'status' => $status,
            'billing_cycle' => $cycle,
            'amount' => $this->price($plan, $cycle, $coupon),
            'currency' => $plan->currency,
            'trial_ends_at' => $status === 'trial' ? now()->addDays($trialDays) : null,
            'current_period_start' => now(),
            'current_period_end' => $status === 'trial' ? now()->addDays($trialDays) : ($cycle === 'yearly' ? now()->addYear() : now()->addMonth()),
            'gateway' => $gateway,
            'gateway_subscription_id' => $gatewayId,
            'coupon_id' => $coupon?->id,
        ]);
        $subscription->tenant_id = $tenant->id;
        $subscription->save();

        $tenant->forceFill(['plan_id' => $plan->id])->save();
        FeatureService::flush();

        return $subscription;
    }

    /** Price of one billing period after a coupon. */
    public function price(Plan $plan, string $cycle, ?Coupon $coupon = null): string
    {
        $units = Money::units($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);

        if ($coupon) {
            $units = match ($coupon->type) {
                'percent', 'first_month' => $units - Money::percent($units, $coupon->value),
                'fixed' => max(0, $units - Money::units($coupon->value)),
                default => $units,
            };
        }

        return Money::decimal(max(0, $units));
    }

    public function findCoupon(?string $code, Plan $plan, Tenant $tenant): ?Coupon
    {
        if (! $code) {
            return null;
        }

        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if (! $coupon || ! $coupon->isRedeemable($plan->id)) {
            throw ValidationException::withMessages(['coupon' => __('This coupon is not valid for the selected plan.')]);
        }

        if (DB::table('coupon_redemptions')->where('coupon_id', $coupon->id)->where('tenant_id', $tenant->id)->exists()) {
            throw ValidationException::withMessages(['coupon' => __('This coupon was already used for this workspace.')]);
        }

        return $coupon;
    }

    /** Upgrade or downgrade. Used directly in manual billing mode and from gateway webhooks. */
    public function changePlan(Tenant $tenant, Plan $plan, string $cycle = 'monthly', ?Coupon $coupon = null, ?string $gateway = null, ?string $gatewayId = null, bool $paid = false): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan, $cycle, $coupon, $gateway, $gatewayId, $paid) {
            $this->query()->where('tenant_id', $tenant->id)->whereIn('status', ['trial', 'active', 'past_due'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => now()]);

            $trialExtension = $coupon?->type === 'trial_extension' ? (int) $coupon->value : 0;
            $subscription = $this->open($tenant, $plan, $cycle, $trialExtension > 0 ? 'trial' : 'active', $trialExtension, $coupon, $gateway, $gatewayId);

            if ($coupon) {
                DB::table('coupon_redemptions')->insert(['coupon_id' => $coupon->id, 'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now()]);
                $coupon->increment('used_count');
            }

            if (Money::units($subscription->amount) > 0 && $subscription->status === 'active') {
                $this->invoice($subscription, $paid ? 'paid' : 'pending', __(':plan plan (:cycle)', ['plan' => $plan->name, 'cycle' => $cycle]));
            }

            SubscriptionChanged::dispatch($subscription);

            return $subscription;
        });
    }

    public function invoice(Subscription $subscription, string $status, string $description, ?string $reference = null): SubscriptionInvoice
    {
        $invoice = new SubscriptionInvoice([
            'subscription_id' => $subscription->id,
            'number' => 'SUB-'.now()->format('Ymd').'-'.str_pad((string) (SubscriptionInvoice::withoutGlobalScope('tenant')->max('id') + 1), 5, '0', STR_PAD_LEFT),
            'description' => $description,
            'amount' => $subscription->amount,
            'currency' => $subscription->currency,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : null,
            'gateway_reference' => $reference,
        ]);
        $invoice->tenant_id = $subscription->tenant_id;
        $invoice->save();

        return $invoice;
    }

    /** Cancel at period end: the workspace keeps access until the paid period is over. */
    public function cancel(Tenant $tenant): ?Subscription
    {
        $subscription = $this->current($tenant);

        if ($subscription && in_array($subscription->status, ['trial', 'active', 'past_due'], true)) {
            // Card-billed subscription: tell Stripe to stop renewing at the end of the paid period
            if ($subscription->gateway === 'stripe' && $subscription->gateway_subscription_id && config('services.stripe.secret')) {
                Http::withToken(config('services.stripe.secret'))->asForm()
                    ->post('https://api.stripe.com/v1/subscriptions/'.$subscription->gateway_subscription_id, ['cancel_at_period_end' => 'true']);
            }

            $subscription->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => $subscription->current_period_end ?? now()])->save();
            SubscriptionChanged::dispatch($subscription);
        }

        return $subscription;
    }

    public function resume(Tenant $tenant): ?Subscription
    {
        $subscription = $this->current($tenant);

        if ($subscription && $subscription->status === 'cancelled' && $subscription->ends_at?->isFuture()) {
            $subscription->forceFill(['status' => 'active', 'cancelled_at' => null, 'ends_at' => null])->save();
            SubscriptionChanged::dispatch($subscription);
        }

        return $subscription;
    }

    /**
     * Scheduled: end trials, renew manual subscriptions, expire cancelled ones.
     * Gateway-billed subscriptions are renewed by their webhooks instead.
     */
    public function processRenewals(): array
    {
        $result = ['renewed' => 0, 'expired' => 0];
        $default = $this->defaultPlan();

        foreach ($this->query()->with('plan')->whereIn('status', ['trial', 'active', 'cancelled'])->get() as $subscription) {
            $tenant = Tenant::find($subscription->tenant_id);

            if (! $tenant) {
                continue;
            }

            $ended = ($subscription->status === 'trial' && $subscription->trial_ends_at?->isPast())
                || ($subscription->status === 'cancelled' && $subscription->ends_at?->isPast() && $tenant->plan_id === $subscription->plan_id);

            if ($ended) {
                $isFree = Money::units($subscription->plan->price_monthly) === 0;

                if ($subscription->status === 'trial' && $isFree) {
                    $subscription->forceFill(['status' => 'active', 'trial_ends_at' => null, 'current_period_start' => now(), 'current_period_end' => now()->addMonth()])->save();
                } else {
                    $subscription->forceFill(['status' => 'expired', 'ends_at' => now()])->save();

                    if ($default) {
                        $this->open($tenant, $default, 'monthly', 'active');
                    }

                    $result['expired']++;
                }

                continue;
            }

            if ($subscription->status === 'active' && ! $subscription->gateway && $subscription->current_period_end?->isPast()) {
                $start = $subscription->current_period_end;
                $subscription->forceFill([
                    'current_period_start' => $start,
                    'current_period_end' => $subscription->billing_cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth(),
                    // a first-month coupon only applies to the first period
                    'amount' => $subscription->coupon?->type === 'first_month' ? $this->price($subscription->plan, $subscription->billing_cycle) : $subscription->amount,
                ])->save();

                if (Money::units($subscription->amount) > 0) {
                    $this->invoice($subscription, 'pending', __(':plan plan renewal', ['plan' => $subscription->plan->name]));
                }

                $result['renewed']++;
            }
        }

        return $result;
    }

    /** Is card billing through Stripe configured for the platform? */
    public function gatewayEnabled(): bool
    {
        return $this->settings->platform('billing_gateway', 'manual') === 'stripe' && filled(config('services.stripe.secret'));
    }

    /** Stripe Checkout URL for a subscription (price is sent inline, so no Stripe price ids are needed). */
    public function checkoutUrl(Tenant $tenant, Plan $plan, string $cycle, ?Coupon $coupon = null): string
    {
        $decimals = Money::currencyConfig($plan->currency)['decimals'];
        $amount = (int) (Money::units($this->price($plan, $cycle, $coupon)) / (Money::SCALE / (10 ** $decimals)));

        $response = Http::withToken(config('services.stripe.secret'))->asForm()->post('https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'subscription',
            'success_url' => route('settings.billing').'?checkout=success',
            'cancel_url' => route('settings.billing'),
            'client_reference_id' => (string) $tenant->id,
            'customer_email' => $tenant->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($plan->currency),
                    'unit_amount' => $amount,
                    'recurring' => ['interval' => $cycle === 'yearly' ? 'year' : 'month'],
                    'product_data' => ['name' => config('app.name').' '.$plan->name],
                ],
            ]],
            'metadata' => ['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'cycle' => $cycle, 'coupon_id' => $coupon?->id],
            'subscription_data' => ['metadata' => ['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'cycle' => $cycle]],
        ]);

        if ($response->failed() || ! $response->json('url')) {
            throw new RuntimeException('Checkout could not be started: '.$response->json('error.message', 'unknown error'));
        }

        return $response->json('url');
    }

    /** Handle a verified Stripe billing event. */
    public function handleGatewayEvent(array $event): void
    {
        $object = $event['data']['object'] ?? [];
        $type = $event['type'] ?? '';

        if ($type === 'checkout.session.completed' && ($object['mode'] ?? '') === 'subscription') {
            $tenant = Tenant::find($object['metadata']['tenant_id'] ?? 0);
            $plan = Plan::find($object['metadata']['plan_id'] ?? 0);

            if ($tenant && $plan) {
                $coupon = Coupon::find($object['metadata']['coupon_id'] ?? 0);
                $this->changePlan($tenant, $plan, $object['metadata']['cycle'] ?? 'monthly', $coupon, 'stripe', $object['subscription'] ?? null, true);
            }

            return;
        }

        $gatewayId = $type === 'customer.subscription.updated' || $type === 'customer.subscription.deleted' ? ($object['id'] ?? null) : ($object['subscription'] ?? null);
        $subscription = $gatewayId ? $this->query()->with('plan')->where('gateway_subscription_id', $gatewayId)->latest('id')->first() : null;

        if (! $subscription) {
            return;
        }

        match ($type) {
            'invoice.paid' => $this->renewFromGateway($subscription, $object),
            'invoice.payment_failed' => $subscription->forceFill(['status' => 'past_due'])->save(),
            'customer.subscription.deleted' => $subscription->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => $subscription->current_period_end ?? now()])->save(),
            'customer.subscription.updated' => $subscription->forceFill(['status' => match ($object['status'] ?? '') {
                'active' => 'active', 'trialing' => 'trial', 'past_due', 'unpaid' => 'past_due', 'canceled' => 'cancelled', default => $subscription->status,
            }])->save(),
            default => null,
        };

        SubscriptionChanged::dispatch($subscription);
    }

    private function renewFromGateway(Subscription $subscription, array $invoice): void
    {
        // The first invoice is already recorded by changePlan(); only later periods are renewals
        if (($invoice['billing_reason'] ?? '') === 'subscription_create') {
            return;
        }

        $start = $subscription->current_period_end ?? now();
        $subscription->forceFill([
            'status' => 'active', 'current_period_start' => $start,
            'current_period_end' => $subscription->billing_cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth(),
        ])->save();

        $this->invoice($subscription, 'paid', __(':plan plan renewal', ['plan' => $subscription->plan->name]), $invoice['id'] ?? null);
    }

    /** Monthly recurring revenue across all paying workspaces. */
    public function mrr(): float
    {
        return (float) $this->query()->where('status', 'active')->get()
            ->sum(fn ($subscription) => $subscription->billing_cycle === 'yearly' ? (float) $subscription->amount / 12 : (float) $subscription->amount);
    }
}
