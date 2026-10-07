<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\KbArticle;
use App\Models\Plan;
use App\Models\User;
use App\Services\FeatureService;
use App\Services\SettingsService;
use App\Services\TenantService;
use Illuminate\Database\Seeder;

/**
 * Everything a fresh installation needs: permissions, plans with their limits, help articles
 * and the first platform administrator. Run DemoSeeder as well for a filled demo workspace.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        TenantService::syncPermissions();
        $this->plans();
        $this->content();

        app(SettingsService::class)->setPlatform([
            'app_name' => config('app.name'), 'default_plan' => 'free', 'trial_plan' => 'professional', 'trial_days' => 14,
            'registration_enabled' => true, 'billing_gateway' => 'manual', 'default_currency' => 'USD',
        ]);

        // First platform administrator. CHANGE THIS PASSWORD after the first sign-in.
        if (! User::where('is_super_admin', true)->exists()) {
            $admin = new User(['name' => 'Platform Admin', 'email' => env('ADMIN_EMAIL', 'admin@example.com'), 'password' => env('ADMIN_PASSWORD', 'Admin@12345')]);
            $admin->is_super_admin = true;
            $admin->email_verified_at = now();
            $admin->save();
        }
    }

    private function plans(): void
    {
        // limits: users, customers, invoices per month, products, storage MB, locations, projects ("unlimited" = no limit)
        $plans = [
            'free' => ['Free', 'Try invoicing with the essentials', 0, 0, 0, [1, 5, 5, 10, 100, 1, 1], ['business_health']],
            'starter' => ['Starter', 'For freelancers', 9, 90, 0, [2, 100, 100, 100, 1024, 1, 5], ['business_health', 'recurring_invoices', 'customer_portal', 'custom_branding', 'projects', 'time_tracking']],
            'professional' => ['Professional', 'For small businesses', 29, 290, 0, [5, 'unlimited', 1000, 1000, 5120, 3, 50], ['business_health', 'recurring_invoices', 'customer_portal', 'custom_branding', 'projects', 'time_tracking', 'inventory', 'advanced_reports', 'automation']],
            'business' => ['Business', 'For teams', 79, 790, 0, [20, 'unlimited', 'unlimited', 'unlimited', 20480, 10, 'unlimited'], ['business_health', 'recurring_invoices', 'customer_portal', 'custom_branding', 'projects', 'time_tracking', 'inventory', 'advanced_reports', 'automation', 'api_access', 'webhooks']],
            'enterprise' => ['Enterprise', 'Custom limits and support', 199, 1990, 0, ['unlimited', 'unlimited', 'unlimited', 'unlimited', 'unlimited', 'unlimited', 'unlimited'], config('saas.features')],
        ];

        $order = 0;

        foreach ($plans as $slug => [$name, $description, $monthly, $yearly, $trial, $limits, $features]) {
            $plan = Plan::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'price_monthly' => $monthly, 'price_yearly' => $yearly,
                'currency' => 'USD', 'trial_days' => $trial, 'sort_order' => $order++, 'is_active' => true, 'is_public' => true,
            ]);

            foreach (config('saas.limits') as $index => $key) {
                $plan->features()->updateOrCreate(['key' => $key], ['type' => 'limit', 'value' => (string) $limits[$index]]);
            }

            foreach (config('saas.features') as $key) {
                $plan->features()->updateOrCreate(['key' => $key], ['type' => 'boolean', 'value' => in_array($key, $features, true) ? '1' : '0']);
            }
        }

        FeatureService::flush();
    }

    private function content(): void
    {
        $articles = [
            ['Getting started', 'Set up your workspace', "Sign up, verify your email and follow the seven setup steps: business details, currency, tax, branding, modules, your first customer and your first invoice.\n\nYou can change every choice later in Settings. Modules you leave switched off stay hidden until you need them."],
            ['Getting started', 'Invite your team', "Open Team and choose Invite member. Pick a role for the person you invite. They receive an email with a link to join.\n\nRoles decide what each member can see and do. Open Team, Roles to change the permissions of a role or to create your own."],
            ['Invoices', 'Create and send an invoice', "Choose Create, Invoice. Pick a customer, add items from your catalogue or type them in, and check the summary.\n\nSave as draft to keep working, or Save and send to email the invoice with its PDF and a secure payment link. The invoice number is assigned automatically and never repeats."],
            ['Invoices', 'Recurring invoices and reminders', "A recurring invoice creates a new invoice on a schedule: weekly, monthly, quarterly, yearly or every N days.\n\nReminders are sent automatically before the due date, on the due date and after it. Set the days in Settings, Notifications."],
            ['Invoices', 'Customize how invoices look', "Open Settings, Invoice. Choose a template (Classic, Modern, Minimal, Professional or Compact), your colour, the columns to show, payment instructions and bank details.\n\nCustom fields such as Purchase Order or Project Code are added in Settings, Custom fields."],
            ['Payments', 'Record a payment', "Open the invoice and choose Record payment. Enter the amount; a smaller amount than the balance marks the invoice as partially paid.\n\nA payment can be refunded from its own page. The original payment stays in your records and a refund entry is added."],
            ['Payments', 'Accept online payments', "Open Settings, Payments and add Stripe, PayPal or a custom gateway with your own keys. Add the webhook URL shown there to your gateway account.\n\nCustomers pay from the secure invoice link. The invoice is marked paid only after the gateway confirms the payment to the webhook."],
            ['Customers', 'Customers and the customer portal', "Each customer has a profile with revenue, outstanding amount, payment pattern, timeline, notes and documents.\n\nInvite a customer to the portal from their profile. In the portal they can view and pay invoices, answer quotes, download documents and send you messages."],
            ['Customers', 'Import customers from CSV', "Open Customers, Import CSV. Upload the file, match its columns to the fields, check the preview and start the import.\n\nRows with mistakes are skipped and listed with their line number, so you can fix and import them again."],
            ['Inventory', 'Track stock', "Switch on Track inventory for a product and enter its opening stock and minimum stock.\n\nSending an invoice takes the stock out; cancelling it puts the stock back. Use Inventory for purchases, adjustments, damage and transfers between locations. Low stock appears in your action list."],
            ['Reports', 'Reports and exports', "Reports cover revenue, expenses, profit, cash flow, receivables, sales, customers and inventory. Choose a period and filters, then export to PDF, CSV or Excel.\n\nLarge exports are prepared in the background and you are notified when they are ready."],
            ['Reports', 'Business Health and signals', "Business Health reads your invoices, payments, expenses, customers and stock and tells you what needs attention: overdue invoices, late payers, rising costs, low margins, inactive customers and more.\n\nThe health score is the average of plain calculations shown on the page. The cash-flow forecast is a projection, not a guarantee."],
            ['Billing', 'Plans, limits and billing', "Open Settings, Billing to see your plan, your usage against its limits and your billing history. You can upgrade, downgrade or cancel there.\n\nAfter cancelling you keep access until the end of the paid period."],
            ['API', 'API and webhooks', "Create an API token in Settings, API tokens and choose its scopes. Send it as a Bearer token to /api/v1/customers, /api/v1/invoices, /api/v1/payments, /api/v1/products and /api/v1/expenses.\n\nWebhooks notify your own systems about events such as invoice.paid. Each request is signed with HMAC-SHA256 over \"timestamp.body\" using the webhook's signing secret."],
        ];

        foreach ($articles as [$category, $title, $body]) {
            KbArticle::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($title)], ['category' => $category, 'title' => $title, 'body' => $body, 'is_published' => true]);
        }

        BlogPost::firstOrCreate(['slug' => 'get-paid-faster-with-clear-invoices'], [
            'title' => 'Get paid faster with clear invoices',
            'excerpt' => 'Five small changes to your invoices that shorten the time between sending and getting paid.',
            'body' => "Late payment is rarely about unwilling customers. More often the invoice is unclear, arrives late or is hard to pay.\n\nSend the invoice the day the work is done. State the due date as a date, not as \"30 days\". Offer an online payment link. Remind politely before the due date, not only after it. And look at each customer's payment pattern before you agree on terms for the next job.",
            'published_at' => now(),
        ]);
    }
}
