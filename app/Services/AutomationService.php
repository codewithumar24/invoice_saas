<?php

namespace App\Services;

use App\Jobs\CallUrlJob;
use App\Models\AutomationLog;
use App\Models\AutomationRule;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tag;
use App\Support\SafeUrl;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** WHEN trigger, IF conditions, THEN actions. Rules are stored per workspace. */
class AutomationService
{
    public const TRIGGERS = [
        'invoice.created' => 'Invoice is created',
        'invoice.overdue' => 'Invoice becomes overdue',
        'invoice.paid' => 'Invoice is fully paid',
        'payment.received' => 'Payment is received',
        'quote.accepted' => 'Quote is accepted',
        'stock.low' => 'Stock falls below minimum',
        'customer.created' => 'Customer is created',
    ];

    public const FIELDS = [
        'amount' => 'Amount', 'days_overdue' => 'Days overdue', 'currency' => 'Currency',
        'customer_name' => 'Customer name', 'stock' => 'Stock quantity', 'product_name' => 'Product name',
    ];

    public const OPERATORS = ['gt' => 'is greater than', 'gte' => 'is at least', 'lt' => 'is less than', 'lte' => 'is at most', 'eq' => 'equals', 'neq' => 'does not equal', 'contains' => 'contains'];

    public const ACTIONS = [
        'email_customer' => 'Send email to the customer',
        'notify_team' => 'Notify the team',
        'add_tag' => 'Tag the customer',
        'webhook' => 'Call a webhook URL',
    ];

    public function __construct(private NotificationService $notifications, private FeatureService $features, private EmailTemplateService $templates) {}

    /**
     * @param  array  $context  values that conditions and messages can use (amount, days_overdue, customer_name...)
     */
    public function fire(string $trigger, ?Model $subject, array $context = [], ?Customer $customer = null): void
    {
        if (! $this->features->enabled('automation')) {
            return;
        }

        foreach (AutomationRule::where('trigger', $trigger)->where('is_active', true)->get() as $rule) {
            if (! $this->passes($rule->conditions ?? [], $context)) {
                continue;
            }

            try {
                foreach ($rule->actions as $action) {
                    $this->run($action, $trigger, $subject, $context, $customer);
                }

                $this->log($rule, $subject, 'success', null);
            } catch (Throwable $exception) {
                report($exception);
                $this->log($rule, $subject, 'failed', mb_substr($exception->getMessage(), 0, 250));
            }

            $rule->forceFill(['run_count' => $rule->run_count + 1, 'last_run_at' => now()])->save();
        }
    }

    public function passes(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            $actual = $context[$condition['field'] ?? ''] ?? null;
            $expected = $condition['value'] ?? null;

            $ok = match ($condition['operator'] ?? 'eq') {
                'gt' => (float) $actual > (float) $expected,
                'gte' => (float) $actual >= (float) $expected,
                'lt' => (float) $actual < (float) $expected,
                'lte' => (float) $actual <= (float) $expected,
                'neq' => (string) $actual !== (string) $expected,
                'contains' => str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
                default => (string) $actual === (string) $expected,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    private function run(array $action, string $trigger, ?Model $subject, array $context, ?Customer $customer): void
    {
        $text = fn (?string $value) => preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => (string) ($context[$m[1]] ?? ''), (string) $value);

        switch ($action['type'] ?? '') {
            case 'email_customer':
                if ($customer?->email) {
                    if ($subject instanceof Invoice && empty($action['message'])) {
                        $key = $trigger === 'invoice.overdue' ? 'invoice_overdue' : ($trigger === 'payment.received' || $trigger === 'invoice.paid' ? 'payment_received' : 'invoice_sent');
                        $this->notifications->email($customer->email, $key, $this->templates->invoiceVariables($subject) + $context, [], $subject->publicUrl(), __('View invoice'));
                    } else {
                        \Illuminate\Support\Facades\Mail::to($customer->email)->queue(new \App\Mail\TemplateMail(
                            $text($action['subject'] ?? __('A message from :business', ['business' => tenant()->name])),
                            '<p>'.nl2br(e($text($action['message'] ?? ''))).'</p>', $this->notifications->branding()
                        ));
                    }
                }
                break;

            case 'notify_team':
                $this->notifications->team('system', $text($action['message'] ?? self::TRIGGERS[$trigger]), null, $context['url'] ?? null, $action['permission'] ?? null, (bool) ($action['email'] ?? false));
                break;

            case 'add_tag':
                if ($customer && ! empty($action['tag'])) {
                    $customer->tags()->syncWithoutDetaching([Tag::firstOrCreate(['name' => $action['tag']])->id]);
                }
                break;

            case 'webhook':
                if (! empty($action['url']) && SafeUrl::isPublic($action['url'])) {
                    CallUrlJob::dispatch($action['url'], ['trigger' => $trigger, 'data' => $context]);
                }
                break;
        }
    }

    private function log(AutomationRule $rule, ?Model $subject, string $status, ?string $message): void
    {
        AutomationLog::create([
            'automation_rule_id' => $rule->id, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            'status' => $status, 'message' => $message,
        ]);
    }
}
