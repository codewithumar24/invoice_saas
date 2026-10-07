<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    }

    private function money(Blueprint $t, string ...$columns): void
    {
        foreach ($columns as $column) {
            $t->decimal($column, 19, 4)->default(0);
        }
    }

    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('company')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('website')->nullable();
            $t->string('tax_number')->nullable();
            $t->string('currency', 3)->nullable();
            $t->unsignedInteger('payment_terms_days')->nullable();
            $t->text('notes')->nullable();
            $t->string('status')->default('active'); // active, archived
            $t->boolean('portal_enabled')->default(false);
            $t->string('portal_password')->nullable();
            $t->string('portal_invite_token', 64)->nullable()->unique();
            $this->money($t, 'balance');
            $t->timestamp('last_activity_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'email']);
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::create('customer_contacts', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('position')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('type')->default('billing'); // billing, shipping
            $t->string('line1')->nullable();
            $t->string('line2')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postal_code')->nullable();
            $t->string('country', 2)->nullable();
            $t->timestamps();
        });

        Schema::create('customer_notes', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('customer_messages', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('from_customer')->default(false);
            $t->text('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('tags', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('color', 9)->default('#64748B');
            $t->timestamps();
            $t->unique(['tenant_id', 'name']);
        });

        Schema::create('taggables', function (Blueprint $t) {
            $t->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $t->morphs('taggable');
            $t->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });

        Schema::create('custom_fields', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('entity'); // customer, invoice, product
            $t->string('label');
            $t->string('key');
            $t->string('type')->default('text'); // text, number, date, select
            $t->json('options')->nullable();
            $t->boolean('is_required')->default(false);
            $t->boolean('show_on_pdf')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'entity', 'key']);
        });

        Schema::create('custom_field_values', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $t->morphs('model');
            $t->text('value')->nullable();
            $t->timestamps();
            $t->unique(['custom_field_id', 'model_id', 'model_type'], 'cfv_unique');
        });

        Schema::create('attachments', function (Blueprint $t) {
            $this->tenant($t);
            $t->nullableMorphs('attachable');
            $t->string('category')->default('general')->index(); // invoices, receipts, quotes, contracts, customer_files, expense_receipts
            $t->string('disk');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->boolean('visible_to_customer')->default(false);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->decimal('rate', 8, 4)->default(0);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('currency', 3);
            $t->decimal('rate', 19, 8); // 1 unit of currency = rate units of base currency
            $t->date('effective_date');
            $t->timestamps();
            $t->index(['tenant_id', 'currency', 'effective_date']);
        });

        Schema::create('payment_methods', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('type'); // cash, bank_transfer, manual, stripe, paypal, custom
            $t->text('instructions')->nullable();
            $t->text('config')->nullable(); // encrypted JSON (gateway keys)
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('type')->default('product'); // product, service
            $t->string('sku')->nullable();
            $t->string('barcode')->nullable();
            $t->string('name');
            $t->text('description')->nullable();
            $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $this->money($t, 'purchase_price', 'selling_price');
            $t->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $t->string('unit')->nullable();
            $t->boolean('track_inventory')->default(false);
            $t->decimal('stock_quantity', 19, 4)->default(0);
            $t->decimal('minimum_stock', 19, 4)->default(0);
            $t->unsignedInteger('duration_minutes')->nullable();
            $t->string('image_path')->nullable();
            $t->string('status')->default('active'); // active, archived
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'type', 'status']);
            $t->index(['tenant_id', 'sku']);
        });

        Schema::create('product_variants', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('sku')->nullable();
            $this->money($t, 'price');
            $t->timestamps();
        });

        Schema::create('inventory_locations', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('type')->default('warehouse'); // warehouse, store, office, branch
            $t->string('address')->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::create('inventory_stock', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $t->decimal('quantity', 19, 4)->default(0);
            $t->timestamps();
            $t->unique(['product_id', 'location_id']);
        });

        Schema::create('inventory_movements', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $t->foreignId('to_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();
            $t->string('type'); // purchase, sale, return, adjustment, damage, transfer
            $t->decimal('quantity', 19, 4); // signed: positive = stock in, negative = stock out
            $this->money($t, 'unit_cost');
            $t->nullableMorphs('reference');
            $t->string('notes')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'product_id', 'created_at']);
        });

        // Concurrency-safe document numbering (row is locked FOR UPDATE while incrementing)
        Schema::create('number_sequences', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('type'); // invoice, quote, payment
            $t->string('period')->default(''); // "", "2026" or "2026-10"
            $t->unsignedBigInteger('branch_id')->default(0);
            $t->unsignedBigInteger('next_number')->default(1);
            $t->timestamps();
            $t->unique(['tenant_id', 'type', 'period', 'branch_id'], 'number_sequences_unique');
        });

        Schema::create('projects', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->date('start_date')->nullable();
            $t->date('deadline')->nullable();
            $this->money($t, 'budget', 'hourly_rate');
            $t->string('status')->default('active'); // planned, active, on_hold, completed, cancelled, archived
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('quotes', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained();
            $t->string('number');
            $t->string('status')->default('draft'); // draft, sent, viewed, accepted, rejected, expired, converted
            $t->date('issue_date');
            $t->date('expiry_date')->nullable();
            $t->string('currency', 3);
            $t->decimal('exchange_rate', 19, 8)->default(1);
            $t->boolean('tax_inclusive')->default(false);
            $t->string('discount_type')->default('percent');
            $this->money($t, 'discount_value', 'subtotal', 'discount_total', 'tax_total', 'total');
            $t->text('notes')->nullable();
            $t->text('terms')->nullable();
            $t->string('public_token', 64)->unique();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('viewed_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->unsignedBigInteger('converted_invoice_id')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'number']);
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('quote_items', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('quantity', 19, 4)->default(1);
            $t->string('unit')->nullable();
            $this->money($t, 'unit_price');
            $t->decimal('discount_percent', 8, 4)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('tax_rate', 8, 4)->default(0);
            $this->money($t, 'tax_amount', 'line_total');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('recurring_invoices', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained();
            $t->string('title')->nullable();
            $t->string('frequency'); // weekly, monthly, quarterly, yearly, custom
            $t->unsignedInteger('interval_days')->nullable();
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->date('next_run_date')->index();
            $t->date('last_run_date')->nullable();
            $t->string('status')->default('active'); // active, paused, ended
            $t->boolean('auto_send')->default(true);
            $t->unsignedInteger('due_days')->default(14);
            $t->string('currency', 3);
            $t->json('items');
            $t->text('notes')->nullable();
            $t->text('terms')->nullable();
            $t->unsignedInteger('invoices_generated')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('recurring_invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number');
            $t->string('reference')->nullable();
            $t->string('status')->default('draft'); // draft, sent, viewed, partially_paid, paid, overdue, cancelled
            $t->date('issue_date');
            $t->date('due_date');
            $t->string('payment_terms')->nullable();
            $t->string('currency', 3);
            $t->decimal('exchange_rate', 19, 8)->default(1); // historical rate, never recalculated
            $t->boolean('tax_inclusive')->default(false);
            $t->string('discount_type')->default('percent'); // percent, fixed
            $this->money($t, 'discount_value', 'subtotal', 'discount_total', 'tax_total', 'shipping', 'adjustment',
                'total', 'amount_paid', 'balance_due', 'deposit_amount');
            $t->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $t->text('notes')->nullable();
            $t->text('terms')->nullable();
            $t->string('footer')->nullable();
            $t->string('template')->default('classic');
            $t->string('public_token', 64)->unique();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('viewed_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['tenant_id', 'number']);
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'customer_id']);
            $t->index(['tenant_id', 'due_date']);
            $t->index(['tenant_id', 'issue_date']);
        });

        Schema::create('invoice_items', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('quantity', 19, 4)->default(1);
            $t->string('unit')->nullable();
            $this->money($t, 'unit_price');
            $t->decimal('discount_percent', 8, 4)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('tax_rate', 8, 4)->default(0);
            $this->money($t, 'tax_amount', 'line_total');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('invoice_taxes', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->decimal('rate', 8, 4)->default(0);
            $this->money($t, 'amount');
            $t->timestamps();
        });

        Schema::create('invoice_views', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('viewed_at');
        });

        Schema::create('invoice_reminders', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->string('type'); // before_due, due, overdue
            $t->date('sent_on');
            $t->timestamps();
            $t->unique(['invoice_id', 'type', 'sent_on']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('customer_id')->constrained();
            $t->string('number');
            $this->money($t, 'amount', 'refunded_amount');
            $t->string('currency', 3);
            $t->decimal('exchange_rate', 19, 8)->default(1);
            $t->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $t->string('method')->default('manual');
            $t->string('gateway')->nullable();
            $t->string('transaction_id')->nullable();
            $t->string('status')->default('paid'); // pending, processing, paid, failed, refunded, partially_refunded
            $t->timestamp('paid_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['tenant_id', 'paid_at']);
            $t->unique(['tenant_id', 'gateway', 'transaction_id'], 'payments_gateway_txn_unique');
        });

        Schema::create('invoice_payments', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $this->money($t, 'amount');
            $t->timestamps();
        });

        // Ledger of money movements (payments and refunds)
        Schema::create('payment_transactions', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type'); // payment, refund
            $this->money($t, 'amount');
            $t->string('currency', 3);
            $t->string('gateway')->nullable();
            $t->string('gateway_reference')->nullable();
            $t->string('status')->default('completed');
            $t->string('notes')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::create('expense_categories', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
        });

        Schema::create('expenses', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('vendor')->nullable();
            $this->money($t, 'amount', 'tax_amount');
            $t->string('currency', 3);
            $t->decimal('exchange_rate', 19, 8)->default(1);
            $t->date('expense_date');
            $t->string('payment_method')->nullable();
            $t->string('reference')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_recurring')->default(false);
            $t->string('recurring_frequency')->nullable(); // weekly, monthly, quarterly, yearly
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['tenant_id', 'expense_date']);
            $t->index(['tenant_id', 'category_id']);
        });

        Schema::create('expense_attachments', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $t->string('disk');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
        });

        Schema::create('project_tasks', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->date('due_date')->nullable();
            $t->string('priority')->default('normal'); // low, normal, high
            $t->string('status')->default('todo'); // todo, in_progress, done
            $t->timestamps();
        });

        Schema::create('time_entries', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_task_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('description')->nullable();
            $t->date('entry_date');
            $t->timestamp('started_at')->nullable(); // set while the timer segment is running
            $t->unsignedInteger('duration_seconds')->default(0);
            $t->string('status')->default('stopped'); // running, paused, stopped
            $t->boolean('is_billable')->default(true);
            $this->money($t, 'hourly_rate');
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('activity_logs', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $t->nullableMorphs('subject');
            $t->string('event');
            $t->string('description');
            $t->json('properties')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'created_at']);
        });

        // Immutable audit trail: the app only ever inserts into this table
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('impersonator_id')->nullable();
            $t->string('action');
            $t->string('entity_type')->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->string('entity_label')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->nullable()->index();
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::create('webhooks', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('url');
            $t->string('secret');
            $t->json('events');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $t->string('event');
            $t->json('payload');
            $t->unsignedSmallInteger('response_status')->nullable();
            $t->text('response_body')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->string('status')->default('pending'); // pending, success, failed
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });

        Schema::create('api_tokens', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('token_hash', 64)->unique();
            $t->json('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = platform default
            $t->string('key');
            $t->string('subject');
            $t->text('body');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['tenant_id', 'key']);
        });

        Schema::create('business_signals', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('type'); // attention, opportunity, risk, trend
            $t->string('code');
            $t->string('category')->nullable();
            $t->string('title');
            $t->text('message')->nullable();
            $t->string('action_label')->nullable();
            $t->string('action_url')->nullable();
            $t->unsignedTinyInteger('severity')->default(1); // 1 low .. 3 high
            $t->json('data')->nullable();
            $t->date('detected_on');
            $t->timestamp('dismissed_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'detected_on']);
        });

        Schema::create('business_metrics', function (Blueprint $t) {
            $this->tenant($t);
            $t->date('metric_date');
            $t->string('key');
            $t->decimal('value', 19, 4)->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'metric_date', 'key']);
        });

        Schema::create('automation_rules', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('trigger'); // invoice.overdue, stock.low, payment.received, quote.accepted, invoice.created
            $t->json('conditions')->nullable();
            $t->json('actions');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('run_count')->default(0);
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
        });

        Schema::create('automation_logs', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $t->nullableMorphs('subject');
            $t->string('status');
            $t->string('message')->nullable();
            $t->timestamps();
        });

        Schema::create('exports', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->string('format');
            $t->json('filters')->nullable();
            $t->string('status')->default('pending'); // pending, processing, completed, failed
            $t->string('disk')->nullable();
            $t->string('path')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('imports', function (Blueprint $t) {
            $this->tenant($t);
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type'); // customers, products, expenses
            $t->string('disk');
            $t->string('path');
            $t->json('mapping')->nullable();
            $t->string('status')->default('uploaded'); // uploaded, processing, completed, failed
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('imported_rows')->default(0);
            $t->unsignedInteger('failed_rows')->default(0);
            $t->json('errors')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['imports', 'exports', 'automation_logs', 'automation_rules', 'business_metrics', 'business_signals',
            'email_templates', 'api_tokens', 'webhook_deliveries', 'webhooks', 'audit_logs', 'activity_logs', 'time_entries',
            'project_tasks', 'expense_attachments', 'expenses', 'expense_categories', 'payment_transactions',
            'invoice_payments', 'payments', 'invoice_reminders', 'invoice_views', 'invoice_taxes', 'invoice_items', 'invoices',
            'recurring_invoices', 'quote_items', 'quotes', 'projects', 'number_sequences', 'inventory_movements',
            'inventory_stock', 'inventory_locations', 'product_variants', 'products', 'product_categories', 'payment_methods',
            'exchange_rates', 'tax_rates', 'attachments', 'custom_field_values', 'custom_fields', 'taggables', 'tags',
            'customer_messages', 'customer_notes', 'customer_addresses', 'customer_contacts', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
