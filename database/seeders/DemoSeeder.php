<?php

namespace Database\Seeders;

use App\Models\AutomationRule;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\TaxRate;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\BusinessHealthService;
use App\Services\CustomerService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\QuoteService;
use App\Services\SubscriptionService;
use App\Services\TenantService;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * A demo workspace with realistic data, created through the same services the application uses.
 * Sign in with demo@example.com / Demo@12345. Run: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'demo@example.com')->exists()) {
            return;
        }

        // Seeding must not send emails or queue jobs
        Mail::fake();
        Queue::fake();

        $user = new User(['name' => 'Demo Owner', 'email' => 'demo@example.com', 'password' => 'Demo@12345', 'country' => 'US', 'timezone' => 'UTC']);
        $user->email_verified_at = now();
        $user->save();

        $tenant = app(TenantService::class)->create($user, ['name' => 'Northwind Studio', 'country' => 'US', 'timezone' => 'UTC', 'currency' => 'USD']);
        $tenant->update([
            'business_type' => 'agency', 'enabled_modules' => array_keys(config('saas.modules')), 'onboarded_at' => now(), 'onboarding_step' => 7,
            'address' => '48 Harbour Street', 'city' => 'Portland', 'state' => 'OR', 'postal_code' => '97201', 'phone' => '+1 503 555 0142', 'tax_number' => 'US-88-1234567',
        ]);

        // Put the demo on the Business plan so every module can be explored
        app(SubscriptionService::class)->changePlan($tenant, Plan::where('slug', 'business')->firstOrFail(), 'monthly');

        Auth::setUser($user);
        app(TenantContext::class)->set($tenant->refresh());

        $tax = TaxRate::create(['name' => 'Sales tax', 'rate' => 8.5, 'is_default' => true]);

        $customers = collect([
            ['Aurora Fitness', 'Aurora Fitness LLC', 'billing@aurorafitness.test'], ['Maple & Pine Cafe', null, 'hello@maplepine.test'],
            ['Brightline Logistics', 'Brightline Inc.', 'ap@brightline.test'], ['Dr. Sana Malik', 'Malik Dental Care', 'sana@malikdental.test'],
            ['Harbor Books', null, 'orders@harborbooks.test'], ['Kite Labs', 'Kite Labs Ltd', 'finance@kitelabs.test'],
            ['Olive Tree Yoga', null, 'studio@olivetree.test'], ['Summit Outdoor', 'Summit Outdoor Co.', 'accounts@summitoutdoor.test'],
        ])->map(fn ($c) => app(CustomerService::class)->create(['name' => $c[0], 'company' => $c[1], 'email' => $c[2], 'payment_terms_days' => 14,
            'address' => ['line1' => fake()->streetAddress(), 'city' => fake()->city(), 'country' => 'US']]));

        $services = collect([['Website design', 1800], ['Monthly maintenance', 350], ['SEO audit', 600], ['Consulting hour', 95]])
            ->map(fn ($s) => Product::create(['type' => 'service', 'name' => $s[0], 'selling_price' => $s[1], 'unit' => $s[0] === 'Consulting hour' ? 'hour' : 'job', 'tax_rate_id' => $tax->id]));

        $inventory = app(InventoryService::class);
        $products = collect([['Branded notebook', 'NB-001', 4, 12, 60, 20], ['Poster print A2', 'PP-A2', 6, 25, 14, 15], ['USB drive 32GB', 'USB-32', 5, 18, 8, 10], ['Sticker pack', 'ST-010', 1, 6, 200, 40]])
            ->map(function ($p) use ($tax, $inventory) {
                $product = Product::create(['type' => 'product', 'name' => $p[0], 'sku' => $p[1], 'purchase_price' => $p[2], 'selling_price' => $p[3], 'unit' => 'pcs', 'track_inventory' => true, 'minimum_stock' => $p[5], 'tax_rate_id' => $tax->id]);
                $inventory->move($product, $inventory->defaultLocation()->id, 'adjustment', (string) $p[4], ['notes' => 'Opening stock']);

                return $product;
            });

        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);

        // Five months of invoices: older ones paid (some late), recent ones open, a few overdue
        foreach (range(150, 0, 6) as $step => $daysAgo) {
            $customer = $customers[$step % $customers->count()];
            $issue = now()->subDays($daysAgo)->startOfDay();
            $service = $services[$step % $services->count()];
            $items = [['product_id' => $service->id, 'name' => $service->name, 'quantity' => $service->unit === 'hour' ? 6 + $step % 5 : 1, 'unit' => $service->unit, 'unit_price' => $service->selling_price, 'tax_rate_id' => $tax->id]];

            if ($step % 3 === 0) {
                $product = $products[$step % $products->count()];
                $items[] = ['product_id' => $product->id, 'name' => $product->name, 'quantity' => 2 + $step % 4, 'unit' => 'pcs', 'unit_price' => $product->selling_price, 'tax_rate_id' => $tax->id];
            }

            $invoice = $invoices->create(['customer_id' => $customer->id, 'issue_date' => $issue->toDateString(), 'due_date' => $issue->copy()->addDays(14)->toDateString(), 'currency' => 'USD', 'discount_type' => 'percent', 'items' => $items]);
            $invoice->forceFill(['created_at' => $issue])->saveQuietly();

            if ($daysAgo === 0) {
                continue; // today's invoice stays a draft
            }

            $invoices->send($invoice, false);

            if ($daysAgo > 30 || $step % 4 === 0) {
                // Some customers pay late: that is what the "late payer" signal picks up
                $paidAt = $issue->copy()->addDays($step % 3 === 1 ? 21 : 9)->min(now());
                $payment = $payments->record($invoice, ['amount' => $invoice->balance_due, 'paid_at' => $paidAt, 'method' => 'bank_transfer']);
                $payment->forceFill(['created_at' => $paidAt])->saveQuietly();
                $invoice->refresh()->forceFill(['paid_at' => $paidAt])->saveQuietly();
            } elseif ($step % 5 === 2) {
                $payments->record($invoice, ['amount' => round((float) $invoice->total / 2, 2), 'paid_at' => now()->subDays(2), 'method' => 'cash']);
            }
        }

        $categories = ExpenseCategory::pluck('id', 'name');
        $expenses = app(ExpenseService::class);
        foreach (range(4, 0) as $monthsAgo) {
            $date = now()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
            $expenses->create(['category_id' => $categories['Rent'], 'vendor' => 'Harbour Offices', 'amount' => 1200, 'currency' => 'USD', 'expense_date' => $date->copy()->addDays(1)->toDateString(), 'is_recurring' => true, 'recurring_frequency' => 'monthly']);
            // Software costs creep up month by month: the "expense increase" signal
            $expenses->create(['category_id' => $categories['Software'], 'vendor' => 'Cloud Tools Inc.', 'amount' => 180 + (4 - $monthsAgo) * 70, 'currency' => 'USD', 'expense_date' => $date->copy()->addDays(3)->min(now())->toDateString(), 'is_recurring' => true, 'recurring_frequency' => 'monthly']);
            $expenses->create(['category_id' => $categories['Marketing'], 'vendor' => 'Adwise', 'amount' => 240 + $monthsAgo * 15, 'currency' => 'USD', 'expense_date' => $date->copy()->addDays(5)->min(now())->toDateString()]);
        }
        $expenses->create(['vendor' => 'Corner Hardware', 'amount' => 64.5, 'currency' => 'USD', 'expense_date' => now()->subDays(3)->toDateString(), 'notes' => 'Needs a category']);

        $quotes = app(QuoteService::class);
        $quote = $quotes->create(['customer_id' => $customers[5]->id, 'issue_date' => now()->subDays(4)->toDateString(), 'expiry_date' => now()->addDays(26)->toDateString(), 'currency' => 'USD', 'discount_type' => 'percent',
            'items' => [['product_id' => $services[0]->id, 'name' => 'Website redesign', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 4200, 'tax_rate_id' => $tax->id]]]);
        $quotes->send($quote, false);
        $accepted = $quotes->create(['customer_id' => $customers[2]->id, 'issue_date' => now()->subDays(9)->toDateString(), 'expiry_date' => now()->addDays(21)->toDateString(), 'currency' => 'USD', 'discount_type' => 'percent',
            'items' => [['product_id' => $services[2]->id, 'name' => 'SEO audit', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 600, 'tax_rate_id' => $tax->id]]]);
        $quotes->send($accepted, false);
        $quotes->accept($accepted);

        $project = Project::create(['customer_id' => $customers[5]->id, 'name' => 'Kite Labs website', 'start_date' => now()->subDays(20), 'deadline' => now()->addDays(25), 'budget' => 4200, 'hourly_rate' => 95, 'status' => 'active']);
        $design = $project->tasks()->create(['title' => 'Homepage design', 'assigned_to' => $user->id, 'priority' => 'high', 'status' => 'in_progress', 'due_date' => now()->addDays(5)]);
        $project->tasks()->create(['title' => 'Content migration', 'priority' => 'normal', 'status' => 'todo', 'due_date' => now()->addDays(15)]);
        foreach ([2.5, 4, 3.25] as $index => $hours) {
            TimeEntry::create(['project_id' => $project->id, 'project_task_id' => $design->id, 'user_id' => $user->id, 'description' => 'Design work', 'entry_date' => now()->subDays($index + 1), 'duration_seconds' => (int) ($hours * 3600), 'status' => 'stopped', 'is_billable' => true, 'hourly_rate' => 95]);
        }

        RecurringInvoice::create(['customer_id' => $customers[0]->id, 'title' => 'Monthly maintenance', 'frequency' => 'monthly', 'start_date' => now()->addDays(5), 'next_run_date' => now()->addDays(5), 'due_days' => 14, 'currency' => 'USD', 'auto_send' => true,
            'items' => [['product_id' => $services[1]->id, 'name' => 'Monthly maintenance', 'quantity' => 1, 'unit' => 'job', 'unit_price' => 350, 'tax_rate_id' => $tax->id]], 'created_by' => $user->id]);

        AutomationRule::create(['name' => 'Chase large overdue invoices', 'trigger' => 'invoice.overdue', 'conditions' => [['field' => 'amount', 'operator' => 'gt', 'value' => '1000']],
            'actions' => [['type' => 'email_customer'], ['type' => 'notify_team', 'message' => 'Invoice {{invoice_number}} for {{customer_name}} is overdue.'], ['type' => 'add_tag', 'tag' => 'Late Payer']], 'is_active' => true]);

        $invoices->markOverdue();
        app(BusinessHealthService::class)->generate();
        Customer::query()->each(fn ($customer) => app(CustomerService::class)->refreshBalance($customer));
    }
}
