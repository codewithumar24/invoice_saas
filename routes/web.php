<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SettingsResourceController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

/* =====================================================================
 | Public website
 * =================================================================== */
Route::middleware('locale')->group(function () {
    Route::get('/', [PublicController::class, 'home'])->name('home');
    Route::get('features', [PublicController::class, 'features'])->name('features');
    Route::get('pricing', [PublicController::class, 'pricing'])->name('pricing');
    Route::get('solutions/{type?}', [PublicController::class, 'solutions'])->name('solutions');
    Route::get('about', [PublicController::class, 'about'])->name('about');
    Route::get('contact', [PublicController::class, 'contact'])->name('contact');
    Route::post('contact', [PublicController::class, 'sendContact'])->name('contact.send')->middleware('throttle:sensitive');
    Route::get('blog', [PublicController::class, 'blog'])->name('blog');
    Route::get('blog/{slug}', [PublicController::class, 'blogPost'])->name('blog.show');
    Route::get('help', [PublicController::class, 'help'])->name('help');
    Route::get('help/{slug}', [PublicController::class, 'helpArticle'])->name('help.show');

    foreach (['terms', 'privacy', 'cookies', 'refund-policy', 'acceptable-use', 'data-processing', 'security'] as $page) {
        Route::get($page, [PublicController::class, 'legal'])->name('legal.'.$page);
    }
});

Route::get('sitemap.xml', [PublicController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [PublicController::class, 'robots']);
Route::get('health', [PublicController::class, 'health'])->name('health');
Route::get('locale/{locale}', [PublicController::class, 'locale'])->name('locale');
Route::get('r/{code}', [PublicController::class, 'referral'])->name('referral');

/* =====================================================================
 | Public share links (random token, rate limited) and gateway webhooks
 * =================================================================== */
Route::middleware('throttle:public')->group(function () {
    Route::get('invoice/public/{token}', [PublicDocumentController::class, 'invoice'])->name('public.invoice.show');
    Route::get('invoice/public/{token}/pdf', [PublicDocumentController::class, 'invoicePdf'])->name('public.invoice.pdf');
    Route::post('invoice/public/{token}/pay/{method}', [PublicDocumentController::class, 'pay'])->name('public.invoice.pay');
    Route::get('quote/public/{token}', [PublicDocumentController::class, 'quote'])->name('public.quote.show');
    Route::get('quote/public/{token}/pdf', [PublicDocumentController::class, 'quotePdf'])->name('public.quote.pdf');
    Route::post('quote/public/{token}/respond', [PublicDocumentController::class, 'quoteRespond'])->name('public.quote.respond');
});

Route::middleware('throttle:webhooks')->group(function () {
    Route::post('webhooks/billing/stripe', [WebhookController::class, 'billing'])->name('webhooks.billing');
    Route::post('webhooks/payments/{provider}/{tenant:slug}', [WebhookController::class, 'payments'])->name('webhooks.payments');
});

/* =====================================================================
 | Authentication (fully custom)
 * =================================================================== */
Route::middleware(['guest', 'locale'])->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login');
    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'register'])->middleware('throttle:sensitive');
    Route::get('forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'sendLink'])->name('password.email')->middleware('throttle:login');
    Route::get('reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'reset'])->name('password.update')->middleware('throttle:login');
    Route::get('two-factor-challenge', [LoginController::class, 'showChallenge'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [LoginController::class, 'challenge'])->middleware('throttle:sensitive');
});

Route::get('invitations/{token}', [RegisterController::class, 'showInvitation'])->name('invitations.show');
Route::post('invitations/{token}', [RegisterController::class, 'acceptInvitation'])->name('invitations.accept')->middleware('throttle:sensitive');
Route::get('email/verify/{id}/{hash}', [RegisterController::class, 'verify'])->name('verification.verify')->middleware(['signed', 'throttle:sensitive']);

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('email/verify', [RegisterController::class, 'verificationNotice'])->name('verification.notice');
    Route::post('email/verification-notification', [RegisterController::class, 'resendVerification'])->name('verification.send')->middleware('throttle:sensitive');
    Route::post('impersonation/stop', [Admin\TenantController::class, 'stopImpersonating'])->name('impersonation.stop');
});

/* =====================================================================
 | Signed-in area without a workspace requirement
 * =================================================================== */
Route::middleware(['auth', 'verified', 'locale'])->group(function () {
    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('workspaces/{tenant}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
});

/* =====================================================================
 | Workspace application
 | auth -> verified email -> tenant resolved -> locale -> 2FA policy
 * =================================================================== */
Route::middleware(['auth', 'verified', 'tenant', 'locale', '2fa-setup'])->group(function () {
    // Personal account
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('profile', [AccountController::class, 'profile'])->name('profile');
        Route::put('profile', [AccountController::class, 'updateProfile'])->name('profile.update');
        Route::get('security', [AccountController::class, 'security'])->name('security');
        Route::put('password', [AccountController::class, 'updatePassword'])->name('password')->middleware('throttle:sensitive');
        Route::delete('sessions/{id}', [AccountController::class, 'destroySession'])->name('sessions.destroy');
        Route::post('sessions/logout-others', [AccountController::class, 'logoutOtherSessions'])->name('sessions.logout-others');
        Route::get('notifications', [AccountController::class, 'notifications'])->name('notifications');
        Route::put('notifications', [AccountController::class, 'updateNotifications'])->name('notifications.update');
    });

    Route::prefix('two-factor')->name('two-factor.')->middleware('throttle:sensitive')->group(function () {
        Route::post('enable', [AccountController::class, 'enableTwoFactor'])->name('enable');
        Route::post('confirm', [AccountController::class, 'confirmTwoFactor'])->name('confirm');
        Route::post('recovery-codes', [AccountController::class, 'regenerateRecoveryCodes'])->name('recovery-codes');
        Route::delete('/', [AccountController::class, 'disableTwoFactor'])->name('disable');
    });

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('onboarding/{step}', [OnboardingController::class, 'store'])->name('onboarding.store')->whereNumber('step');

    Route::middleware('onboarded')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:dashboard.view');
        Route::post('dashboard/widgets', [DashboardController::class, 'saveWidgets'])->name('dashboard.widgets');

        Route::middleware(['feature:business_health', 'permission:health.view'])->group(function () {
            Route::get('business-health', [DashboardController::class, 'health'])->name('health.index');
            Route::post('business-health/refresh', [DashboardController::class, 'refreshHealth'])->name('health.refresh')->middleware('throttle:sensitive');
            Route::post('signals/{signal}/dismiss', [DashboardController::class, 'dismissSignal'])->name('signals.dismiss');
        });

        Route::get('search', [NotificationController::class, 'search'])->name('search');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::get('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('announcements/{announcement}/dismiss', [NotificationController::class, 'dismissAnnouncement'])->name('announcements.dismiss');

        /* ---------- Customers ---------- */
        Route::post('customers/bulk', [CustomerController::class, 'bulk'])->name('customers.bulk');
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
        Route::post('customers/{customer}/notes', [CustomerController::class, 'storeNote'])->name('customers.notes.store');
        Route::post('customers/{customer}/messages', [CustomerController::class, 'storeMessage'])->name('customers.messages.store');
        Route::post('customers/{customer}/documents', [CustomerController::class, 'storeDocument'])->name('customers.documents.store');
        Route::post('customers/{customer}/portal', [CustomerController::class, 'invitePortal'])->name('customers.portal.invite');
        Route::delete('customers/{customer}/portal', [CustomerController::class, 'disablePortal'])->name('customers.portal.disable');

        /* ---------- Invoices ---------- */
        Route::middleware('feature:invoicing')->group(function () {
            Route::post('invoices/bulk', [InvoiceController::class, 'bulk'])->name('invoices.bulk');
            Route::resource('invoices', InvoiceController::class);
            Route::get('invoices/{invoice}/email-preview', [InvoiceController::class, 'emailPreview'])->name('invoices.email-preview');
            Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
            Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
            Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
            Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
            Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
            Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
            Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store');

            Route::middleware(['feature:recurring_invoices', 'permission:invoices.create'])->group(function () {
                Route::resource('recurring', RecurringInvoiceController::class)->except('show');
                Route::post('recurring/{recurring}/toggle', [RecurringInvoiceController::class, 'toggle'])->name('recurring.toggle');
                Route::post('recurring/{recurring}/run', [RecurringInvoiceController::class, 'run'])->name('recurring.run');
            });
        });

        /* ---------- Quotes ---------- */
        Route::middleware('feature:quotes')->group(function () {
            Route::resource('quotes', QuoteController::class);
            Route::post('quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
            Route::post('quotes/{quote}/respond', [QuoteController::class, 'respond'])->name('quotes.respond');
            Route::post('quotes/{quote}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
            Route::get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');
        });

        /* ---------- Payments ---------- */
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund')->middleware('throttle:sensitive');

        /* ---------- Expenses ---------- */
        Route::middleware('feature:expenses')->group(function () {
            Route::post('expenses/bulk', [ExpenseController::class, 'bulk'])->name('expenses.bulk');
            Route::post('expense-categories', [ExpenseController::class, 'storeCategory'])->name('expenses.categories.store');
            Route::delete('expense-categories/{category}', [ExpenseController::class, 'destroyCategory'])->name('expenses.categories.destroy');
            Route::get('expense-receipts/{receipt}', [ExpenseController::class, 'receipt'])->name('expenses.receipts.show');
            Route::delete('expense-receipts/{receipt}', [ExpenseController::class, 'destroyReceipt'])->name('expenses.receipts.destroy');
            Route::resource('expenses', ExpenseController::class)->except('show');
        });

        /* ---------- Products, services and inventory ---------- */
        Route::middleware('feature:products')->group(function () {
            Route::post('products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
            Route::post('product-categories', [ProductController::class, 'storeCategory'])->name('products.categories.store');
            Route::resource('products', ProductController::class);
        });

        Route::middleware('feature:inventory')->prefix('inventory')->name('inventory.')->group(function () {
            Route::middleware('permission:inventory.view,inventory.manage')->group(function () {
                Route::get('/', [InventoryController::class, 'index'])->name('index');
                Route::get('movements', [InventoryController::class, 'movements'])->name('movements');
            });
            Route::middleware('permission:inventory.manage')->group(function () {
                Route::post('adjust', [InventoryController::class, 'adjust'])->name('adjust');
                Route::post('transfer', [InventoryController::class, 'transfer'])->name('transfer');
                Route::post('locations', [InventoryController::class, 'storeLocation'])->name('locations.store');
                Route::delete('locations/{location}', [InventoryController::class, 'destroyLocation'])->name('locations.destroy');
            });
        });

        /* ---------- Projects and time tracking ---------- */
        Route::middleware('feature:projects')->group(function () {
            Route::resource('projects', ProjectController::class);
            Route::post('projects/{project}/invoice', [ProjectController::class, 'invoice'])->name('projects.invoice');
            Route::post('projects/{project}/tasks', [ProjectController::class, 'storeTask'])->name('projects.tasks.store');
            Route::put('tasks/{task}', [ProjectController::class, 'updateTask'])->name('tasks.update');
            Route::delete('tasks/{task}', [ProjectController::class, 'destroyTask'])->name('tasks.destroy');

            Route::middleware('permission:time.track,projects.manage')->prefix('time')->name('time.')->group(function () {
                Route::get('/', [TimeEntryController::class, 'index'])->name('index');
                Route::post('/', [TimeEntryController::class, 'store'])->name('store');
                Route::post('start', [TimeEntryController::class, 'start'])->name('start');
                Route::post('{entry}/pause', [TimeEntryController::class, 'pause'])->name('pause');
                Route::post('{entry}/resume', [TimeEntryController::class, 'resume'])->name('resume');
                Route::post('{entry}/stop', [TimeEntryController::class, 'stop'])->name('stop');
                Route::delete('{entry}', [TimeEntryController::class, 'destroy'])->name('destroy');
            });
        });

        /* ---------- Reports, exports, imports ---------- */
        Route::middleware(['feature:reports', 'permission:reports.view'])->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        });

        Route::middleware('permission:reports.export')->group(function () {
            Route::get('exports', [ExportController::class, 'index'])->name('exports.index');
            Route::post('exports', [ExportController::class, 'store'])->name('exports.store');
            Route::get('exports/download', [ExportController::class, 'downloadNow'])->name('exports.download-now');
            Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
        });

        Route::middleware('permission:customers.create,products.create,expenses.create')->group(function () {
            Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
            Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
            Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
            Route::post('imports/{import}/start', [ImportController::class, 'start'])->name('imports.start');
        });

        /* ---------- Automation ---------- */
        Route::middleware(['feature:automation', 'permission:automation.manage'])->group(function () {
            Route::resource('automation', AutomationController::class)->except('show');
            Route::post('automation/{automation}/toggle', [AutomationController::class, 'toggle'])->name('automation.toggle');
        });

        /* ---------- Documents ---------- */
        Route::middleware('permission:documents.view,documents.manage')->group(function () {
            Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
        });
        Route::middleware('permission:documents.manage')->group(function () {
            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        });

        /* ---------- Team, roles ---------- */
        Route::middleware('permission:team.view,team.manage')->group(function () {
            Route::get('team', [TeamController::class, 'index'])->name('team.index');
            Route::get('team/{user}/activity', [TeamController::class, 'activity'])->name('team.activity');
        });
        Route::middleware('permission:team.manage')->group(function () {
            Route::post('team/invite', [TeamController::class, 'invite'])->name('team.invite')->middleware('throttle:sensitive');
            Route::delete('team/invitations/{invitation}', [TeamController::class, 'cancelInvitation'])->name('team.invitations.destroy');
            Route::put('team/{user}', [TeamController::class, 'updateMember'])->name('team.update');
            Route::post('team/{user}/toggle', [TeamController::class, 'toggleMember'])->name('team.toggle');
            Route::post('team/{user}/reset-access', [TeamController::class, 'resetAccess'])->name('team.reset-access');
            Route::delete('team/{user}', [TeamController::class, 'removeMember'])->name('team.remove');
        });
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [TeamController::class, 'roles'])->name('team.roles');
            Route::get('roles/create', [TeamController::class, 'createRole'])->name('team.roles.create');
            Route::post('roles', [TeamController::class, 'storeRole'])->name('team.roles.store');
            Route::get('roles/{role}/edit', [TeamController::class, 'editRole'])->name('team.roles.edit');
            Route::put('roles/{role}', [TeamController::class, 'updateRole'])->name('team.roles.update');
            Route::delete('roles/{role}', [TeamController::class, 'destroyRole'])->name('team.roles.destroy');
        });

        /* ---------- Support ---------- */
        Route::get('support', [SupportController::class, 'index'])->name('support.index');
        Route::post('support', [SupportController::class, 'store'])->name('support.store')->middleware('throttle:sensitive');
        Route::get('support/{ticket}', [SupportController::class, 'show'])->name('support.show');
        Route::post('support/{ticket}/reply', [SupportController::class, 'reply'])->name('support.reply');
        Route::get('support/{ticket}/attachments/{message}', [SupportController::class, 'attachment'])->name('support.attachment');

        /* ---------- Settings ---------- */
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::middleware('permission:settings.view,settings.update')->group(function () {
                Route::get('/', fn () => redirect()->route('settings.business'))->name('index');
                Route::get('business', [SettingsController::class, 'business'])->name('business');
                Route::get('branding', [SettingsController::class, 'branding'])->name('branding');
                Route::get('invoices', [SettingsController::class, 'invoices'])->name('invoices');
                Route::get('tax', [SettingsResourceController::class, 'tax'])->name('tax');
                Route::get('currency', [SettingsController::class, 'currency'])->name('currency');
                Route::get('notifications', [SettingsController::class, 'notifications'])->name('notifications');
                Route::get('payments', [SettingsResourceController::class, 'payments'])->name('payments');
                Route::get('security', [SettingsController::class, 'security'])->name('security');
                Route::get('modules', [SettingsController::class, 'modules'])->name('modules');
                Route::get('custom-fields', [SettingsResourceController::class, 'customFields'])->name('custom-fields');
                Route::get('tags', [SettingsResourceController::class, 'tags'])->name('tags');
                Route::get('branches', [SettingsResourceController::class, 'branches'])->name('branches');
                Route::get('email-templates', [SettingsResourceController::class, 'emailTemplates'])->name('email-templates');
                Route::get('email-templates/{key}', [SettingsResourceController::class, 'editEmailTemplate'])->name('email-templates.edit');
                Route::get('data', [SettingsController::class, 'data'])->name('data');
            });

            Route::middleware('permission:settings.update')->group(function () {
                Route::put('business', [SettingsController::class, 'updateBusiness'])->name('business.update');
                Route::put('branding', [SettingsController::class, 'updateBranding'])->name('branding.update');
                Route::put('invoices', [SettingsController::class, 'updateInvoices'])->name('invoices.update');
                Route::put('tax', [SettingsResourceController::class, 'updateTax'])->name('tax.update');
                Route::post('tax-rates', [SettingsResourceController::class, 'storeTaxRate'])->name('tax-rates.store');
                Route::delete('tax-rates/{rate}', [SettingsResourceController::class, 'destroyTaxRate'])->name('tax-rates.destroy');
                Route::put('currency', [SettingsController::class, 'updateCurrency'])->name('currency.update');
                Route::post('exchange-rates', [SettingsResourceController::class, 'storeExchangeRate'])->name('exchange-rates.store');
                Route::delete('exchange-rates/{rate}', [SettingsResourceController::class, 'destroyExchangeRate'])->name('exchange-rates.destroy');
                Route::put('notifications', [SettingsController::class, 'updateNotifications'])->name('notifications.update');
                Route::post('payment-methods', [SettingsResourceController::class, 'storePaymentMethod'])->name('payment-methods.store');
                Route::post('payment-methods/{method}/toggle', [SettingsResourceController::class, 'togglePaymentMethod'])->name('payment-methods.toggle');
                Route::delete('payment-methods/{method}', [SettingsResourceController::class, 'destroyPaymentMethod'])->name('payment-methods.destroy');
                Route::put('security', [SettingsController::class, 'updateSecurity'])->name('security.update');
                Route::put('modules', [SettingsController::class, 'updateModules'])->name('modules.update');
                Route::post('custom-fields', [SettingsResourceController::class, 'storeCustomField'])->name('custom-fields.store');
                Route::delete('custom-fields/{field}', [SettingsResourceController::class, 'destroyCustomField'])->name('custom-fields.destroy');
                Route::post('tags', [SettingsResourceController::class, 'storeTag'])->name('tags.store');
                Route::delete('tags/{tag}', [SettingsResourceController::class, 'destroyTag'])->name('tags.destroy');
                Route::post('branches', [SettingsResourceController::class, 'storeBranch'])->name('branches.store');
                Route::delete('branches/{branch}', [SettingsResourceController::class, 'destroyBranch'])->name('branches.destroy');
                Route::put('email-templates/{key}', [SettingsResourceController::class, 'updateEmailTemplate'])->name('email-templates.update');
                Route::post('data/deletion', [SettingsController::class, 'requestDeletion'])->name('data.deletion')->middleware('throttle:sensitive');
                Route::delete('data/deletion', [SettingsController::class, 'cancelDeletion'])->name('data.deletion.cancel');
            });

            Route::get('audit-log', [SettingsController::class, 'audit'])->name('audit')->middleware('permission:audit.view');

            Route::middleware('permission:billing.manage')->group(function () {
                Route::get('billing', [BillingController::class, 'index'])->name('billing');
                Route::post('billing/change', [BillingController::class, 'change'])->name('billing.change')->middleware('throttle:sensitive');
                Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
                Route::post('billing/resume', [BillingController::class, 'resume'])->name('billing.resume');
            });

            Route::middleware(['feature:webhooks', 'permission:api.manage'])->group(function () {
                Route::get('webhooks', [SettingsResourceController::class, 'webhooks'])->name('webhooks');
                Route::post('webhooks', [SettingsResourceController::class, 'storeWebhook'])->name('webhooks.store');
                Route::get('webhooks/{webhook}', [SettingsResourceController::class, 'showWebhook'])->name('webhooks.show');
                Route::post('webhooks/{webhook}/toggle', [SettingsResourceController::class, 'toggleWebhook'])->name('webhooks.toggle');
                Route::delete('webhooks/{webhook}', [SettingsResourceController::class, 'destroyWebhook'])->name('webhooks.destroy');
            });

            Route::middleware(['feature:api_access', 'permission:api.manage'])->group(function () {
                Route::get('api-tokens', [SettingsResourceController::class, 'apiTokens'])->name('api-tokens');
                Route::post('api-tokens', [SettingsResourceController::class, 'storeApiToken'])->name('api-tokens.store')->middleware('throttle:sensitive');
                Route::delete('api-tokens/{token}', [SettingsResourceController::class, 'destroyApiToken'])->name('api-tokens.destroy');
            });
        });
    });
});

/* =====================================================================
 | Customer portal (per business): /portal/{business-slug}/...
 * =================================================================== */
Route::prefix('portal/{tenant}')->name('portal.')->group(function () {
    Route::middleware('portal:guest')->group(function () {
        Route::get('login', [PortalController::class, 'showLogin'])->name('login');
        Route::post('login', [PortalController::class, 'login'])->middleware('throttle:sensitive');
        Route::get('set-password/{token}', [PortalController::class, 'showPassword'])->name('password');
        Route::post('set-password/{token}', [PortalController::class, 'setPassword'])->middleware('throttle:sensitive');
    });

    Route::middleware('portal')->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::post('logout', [PortalController::class, 'logout'])->name('logout');
        Route::get('invoices', [PortalController::class, 'invoices'])->name('invoices');
        Route::get('quotes', [PortalController::class, 'quotes'])->name('quotes');
        Route::post('quotes/{quote}/respond', [PortalController::class, 'quoteRespond'])->name('quotes.respond');
        Route::get('payments', [PortalController::class, 'payments'])->name('payments');
        Route::get('documents', [PortalController::class, 'documents'])->name('documents');
        Route::get('documents/{document}', [PortalController::class, 'download'])->name('documents.download');
        Route::get('profile', [PortalController::class, 'profile'])->name('profile');
        Route::put('profile', [PortalController::class, 'updateProfile'])->name('profile.update');
        Route::get('messages', [PortalController::class, 'messages'])->name('messages');
        Route::post('messages', [PortalController::class, 'sendMessage'])->name('messages.send')->middleware('throttle:sensitive');
    });
});

/* =====================================================================
 | Platform administration (super admins only)
 * =================================================================== */
Route::prefix('admin')->name('admin.')->middleware(['auth', 'super-admin'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('tenants', [Admin\TenantController::class, 'index'])->name('tenants.index');
    Route::get('tenants/{tenant}', [Admin\TenantController::class, 'show'])->name('tenants.show');
    Route::post('tenants/{tenant}/toggle', [Admin\TenantController::class, 'toggle'])->name('tenants.toggle');
    Route::post('tenants/{tenant}/plan', [Admin\TenantController::class, 'changePlan'])->name('tenants.plan');
    Route::post('tenants/{tenant}/impersonate', [Admin\TenantController::class, 'impersonate'])->name('tenants.impersonate');

    Route::get('users', [Admin\DashboardController::class, 'users'])->name('users');
    Route::post('users/{user}/toggle', [Admin\DashboardController::class, 'toggleUser'])->name('users.toggle');

    Route::resource('plans', Admin\PlanController::class)->except('show');
    Route::get('subscriptions', [Admin\TenantController::class, 'subscriptions'])->name('subscriptions');
    Route::get('payments', [Admin\TenantController::class, 'payments'])->name('payments');
    Route::post('payments/{invoice}/paid', [Admin\TenantController::class, 'markPaid'])->name('payments.paid');

    Route::get('coupons', [Admin\PlanController::class, 'coupons'])->name('coupons');
    Route::post('coupons', [Admin\PlanController::class, 'storeCoupon'])->name('coupons.store');
    Route::post('coupons/{coupon}/toggle', [Admin\PlanController::class, 'toggleCoupon'])->name('coupons.toggle');
    Route::delete('coupons/{coupon}', [Admin\PlanController::class, 'destroyCoupon'])->name('coupons.destroy');

    Route::get('feature-flags', [Admin\PlanController::class, 'flags'])->name('flags');
    Route::put('feature-flags/{flag}', [Admin\PlanController::class, 'updateFlag'])->name('flags.update');

    Route::get('announcements', [Admin\ContentController::class, 'announcements'])->name('announcements');
    Route::post('announcements', [Admin\ContentController::class, 'storeAnnouncement'])->name('announcements.store');
    Route::delete('announcements/{announcement}', [Admin\ContentController::class, 'destroyAnnouncement'])->name('announcements.destroy');

    Route::get('email-templates', [Admin\ContentController::class, 'emailTemplates'])->name('email-templates');
    Route::get('email-templates/{key}', [Admin\ContentController::class, 'editEmailTemplate'])->name('email-templates.edit');
    Route::put('email-templates/{key}', [Admin\ContentController::class, 'updateEmailTemplate'])->name('email-templates.update');

    Route::get('knowledge-base', [Admin\ContentController::class, 'articles'])->name('kb');
    Route::get('blog', [Admin\ContentController::class, 'articles'])->name('blog');
    Route::get('articles/{type}/create', [Admin\ContentController::class, 'editArticle'])->name('articles.create');
    Route::post('articles/{type}', [Admin\ContentController::class, 'saveArticle'])->name('articles.store');
    Route::get('articles/{type}/{id}/edit', [Admin\ContentController::class, 'editArticle'])->name('articles.edit');
    Route::put('articles/{type}/{id}', [Admin\ContentController::class, 'saveArticle'])->name('articles.update');
    Route::delete('articles/{type}/{id}', [Admin\ContentController::class, 'destroyArticle'])->name('articles.destroy');

    Route::get('support', [Admin\ContentController::class, 'tickets'])->name('support.index');
    Route::get('support/{ticket}', [Admin\ContentController::class, 'showTicket'])->name('support.show');
    Route::post('support/{ticket}', [Admin\ContentController::class, 'replyTicket'])->name('support.reply');

    Route::get('messages', [Admin\DashboardController::class, 'messages'])->name('messages');
    Route::get('logs', [Admin\DashboardController::class, 'logs'])->name('logs');
    Route::get('queue', [Admin\DashboardController::class, 'queue'])->name('queue');
    Route::post('queue/{id}/retry', [Admin\DashboardController::class, 'retryJob'])->name('queue.retry');
    Route::delete('queue/{id}', [Admin\DashboardController::class, 'forgetJob'])->name('queue.forget');
    Route::get('backups', [Admin\DashboardController::class, 'backups'])->name('backups');
    Route::post('backups', [Admin\DashboardController::class, 'runBackup'])->name('backups.run');
    Route::get('settings', [Admin\DashboardController::class, 'settings'])->name('settings');
    Route::put('settings', [Admin\DashboardController::class, 'updateSettings'])->name('settings.update');
});
