<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('description')->nullable();
            $t->decimal('price_monthly', 19, 4)->default(0);
            $t->decimal('price_yearly', 19, 4)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->unsignedInteger('trial_days')->default(0);
            $t->boolean('is_active')->default(true);
            $t->boolean('is_public')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });

        // Database-driven plan limits and feature switches (never hard-coded per plan)
        Schema::create('plan_features', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->string('type')->default('limit'); // limit | boolean
            $t->string('value')->nullable(); // number, "unlimited", "1" or "0"
            $t->timestamps();
            $t->unique(['plan_id', 'key']);
        });

        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('business_type')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('website')->nullable();
            $t->string('address')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('postal_code')->nullable();
            $t->string('country', 2)->nullable();
            $t->string('timezone')->default('UTC');
            $t->string('currency', 3)->default('USD');
            $t->string('locale', 5)->default('en');
            $t->string('tax_number')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('favicon_path')->nullable();
            $t->string('primary_color', 9)->default('#0F6B4F');
            $t->string('custom_domain')->nullable()->unique();
            $t->string('status')->default('active')->index(); // active, suspended
            $t->json('enabled_modules')->nullable();
            $t->unsignedTinyInteger('onboarding_step')->default(1);
            $t->timestamp('onboarded_at')->nullable();
            $t->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $t->string('referral_code', 16)->nullable()->unique();
            $t->unsignedBigInteger('referred_by_tenant_id')->nullable();
            $t->unsignedInteger('referral_clicks')->default(0);
            $t->timestamp('deletion_requested_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('country', 2)->nullable();
            $t->string('timezone')->nullable();
            $t->string('locale', 5)->nullable();
            $t->string('theme')->default('light'); // light, dark, system
            $t->string('status')->default('active');
            $t->foreignId('current_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $t->boolean('is_super_admin')->default(false);
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->json('dashboard_config')->nullable();
            $t->json('notification_preferences')->nullable();
        });

        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('type')->default('branch'); // head_office, branch, warehouse
            $t->string('address')->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        Schema::create('tenant_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_owner')->default(false);
            $t->string('status')->default('active'); // active, suspended
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->unique(['tenant_id', 'user_id']);
        });

        // Permissions are global definitions; roles belong to a tenant
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('group')->index();
            $t->timestamps();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('description')->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->unique(['tenant_id', 'slug']);
        });

        Schema::create('role_permissions', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'role_id']);
            $t->index(['tenant_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('email');
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->string('token', 64)->unique();
            $t->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $t->timestamp('accepted_at')->nullable();
            $t->dateTime('expires_at');
            $t->timestamps();
            $t->index(['tenant_id', 'email']);
        });

        Schema::create('login_attempts', function (Blueprint $t) {
            $t->id();
            $t->string('email')->index();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->boolean('successful')->default(false);
            $t->timestamp('created_at')->nullable()->index();
        });

        // Key/value settings; tenant_id null = platform level
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->longText('value')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'key']);
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('type'); // percent, fixed, first_month, trial_extension
            $t->decimal('value', 19, 4)->default(0);
            $t->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->constrained();
            $t->string('status')->default('trial')->index(); // trial, active, past_due, cancelled, expired
            $t->string('billing_cycle')->default('monthly');
            $t->decimal('amount', 19, 4)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->timestamp('trial_ends_at')->nullable();
            $t->timestamp('current_period_start')->nullable();
            $t->timestamp('current_period_end')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->string('gateway')->nullable();
            $t->string('gateway_subscription_id')->nullable()->index();
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('subscription_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number');
            $t->string('description')->nullable();
            $t->decimal('amount', 19, 4)->default(0);
            $t->string('currency', 3)->default('USD');
            $t->string('status')->default('paid'); // paid, failed, pending
            $t->timestamp('paid_at')->nullable();
            $t->string('gateway_reference')->nullable();
            $t->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['coupon_id', 'tenant_id']);
        });

        Schema::create('usage_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('metric');
            $t->string('period', 7); // YYYY-MM
            $t->unsignedBigInteger('quantity')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'metric', 'period']);
        });

        Schema::create('feature_flags', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('name');
            $t->string('description')->nullable();
            $t->boolean('enabled')->default(true);
            $t->json('tenant_overrides')->nullable(); // {"tenantId": true|false}
            $t->timestamps();
        });

        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->string('type')->default('system'); // system, feature, maintenance
            $t->string('target_type')->default('all'); // all, plans, countries, tenants
            $t->json('target_values')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('subject');
            $t->string('category')->default('general');
            $t->string('priority')->default('normal');
            $t->string('status')->default('open')->index(); // open, pending, resolved, closed
            $t->timestamp('last_reply_at')->nullable();
            $t->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_staff')->default(false);
            $t->text('body');
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->timestamps();
        });

        Schema::create('kb_articles', function (Blueprint $t) {
            $t->id();
            $t->string('category')->index();
            $t->string('title');
            $t->string('slug')->unique();
            $t->longText('body');
            $t->boolean('is_published')->default(true);
            $t->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('excerpt', 500)->nullable();
            $t->longText('body');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->text('message');
            $t->timestamps();
        });

        Schema::create('referrals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('referred_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('status')->default('registered'); // registered, converted, rewarded
            $t->decimal('reward', 19, 4)->default(0);
            $t->timestamps();
        });

        // Idempotency store for incoming gateway webhooks
        Schema::create('webhook_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider');
            $t->string('event_id');
            $t->string('type')->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'event_id']);
        });

        Schema::create('backup_logs', function (Blueprint $t) {
            $t->id();
            $t->string('type'); // database, full
            $t->string('status'); // success, failed
            $t->string('path')->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->text('message')->nullable();
            $t->timestamps();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'backup_logs', 'webhook_events', 'referrals', 'contact_messages', 'blog_posts', 'kb_articles',
            'support_messages', 'support_tickets', 'announcements', 'feature_flags', 'usage_records', 'coupon_redemptions',
            'subscription_invoices', 'subscriptions', 'coupons', 'settings', 'login_attempts', 'invitations', 'user_roles',
            'role_permissions', 'roles', 'permissions', 'tenant_user', 'branches'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('current_tenant_id');
            $t->dropColumn(['country', 'timezone', 'locale', 'theme', 'status', 'is_super_admin', 'two_factor_secret',
                'two_factor_recovery_codes', 'two_factor_confirmed_at', 'last_login_at', 'last_login_ip',
                'dashboard_config', 'notification_preferences']);
        });
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
