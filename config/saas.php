<?php

return [
    // Modules a workspace can switch on or off (onboarding step 5 and Settings)
    'modules' => [
        'invoicing' => 'Invoicing', 'payments' => 'Payments', 'expenses' => 'Expenses', 'quotes' => 'Quotes',
        'products' => 'Products & services', 'inventory' => 'Inventory', 'projects' => 'Projects & time tracking',
        'reports' => 'Reports', 'automation' => 'Automation', 'business_health' => 'Business Health',
    ],

    // Business types and the modules/dashboard widgets they start with
    'business_types' => [
        'freelancer' => ['label' => 'Freelancer', 'modules' => ['invoicing', 'payments', 'expenses', 'quotes', 'projects', 'reports', 'business_health'], 'widgets' => ['revenue', 'outstanding', 'overdue', 'profit', 'signals', 'time']],
        'agency' => ['label' => 'Agency', 'modules' => ['invoicing', 'payments', 'expenses', 'quotes', 'projects', 'reports', 'automation', 'business_health'], 'widgets' => ['revenue', 'profit', 'outstanding', 'overdue', 'projects', 'signals']],
        'retail' => ['label' => 'Retail', 'modules' => ['invoicing', 'payments', 'expenses', 'products', 'inventory', 'reports', 'business_health'], 'widgets' => ['revenue', 'profit', 'inventory', 'customers', 'cash_flow', 'signals']],
        'consulting' => ['label' => 'Consulting', 'modules' => ['invoicing', 'payments', 'expenses', 'quotes', 'projects', 'reports', 'business_health'], 'widgets' => ['revenue', 'outstanding', 'overdue', 'profit', 'signals', 'customers']],
        'construction' => ['label' => 'Construction', 'modules' => ['invoicing', 'payments', 'expenses', 'quotes', 'projects', 'products', 'reports', 'business_health'], 'widgets' => ['revenue', 'expenses', 'profit', 'outstanding', 'projects', 'signals']],
        'education' => ['label' => 'Education', 'modules' => ['invoicing', 'payments', 'expenses', 'products', 'reports', 'business_health'], 'widgets' => ['revenue', 'outstanding', 'overdue', 'customers', 'signals', 'cash_flow']],
        'restaurant' => ['label' => 'Restaurant', 'modules' => ['invoicing', 'payments', 'expenses', 'products', 'inventory', 'reports', 'business_health'], 'widgets' => ['revenue', 'expenses', 'profit', 'inventory', 'signals', 'cash_flow']],
        'ecommerce' => ['label' => 'E-commerce', 'modules' => ['invoicing', 'payments', 'expenses', 'products', 'inventory', 'reports', 'automation', 'business_health'], 'widgets' => ['revenue', 'profit', 'inventory', 'customers', 'signals', 'cash_flow']],
        'professional_services' => ['label' => 'Professional services', 'modules' => ['invoicing', 'payments', 'expenses', 'quotes', 'products', 'projects', 'reports', 'business_health'], 'widgets' => ['revenue', 'outstanding', 'overdue', 'profit', 'signals', 'customers']],
        'other' => ['label' => 'Other', 'modules' => ['invoicing', 'payments', 'expenses', 'quotes', 'products', 'reports', 'business_health'], 'widgets' => ['revenue', 'expenses', 'profit', 'outstanding', 'overdue', 'signals']],
    ],

    'widgets' => [
        'revenue' => 'Revenue', 'expenses' => 'Expenses', 'profit' => 'Profit', 'outstanding' => 'Outstanding',
        'overdue' => 'Overdue', 'customers' => 'Customers', 'cash_flow' => 'Cash flow', 'inventory' => 'Inventory',
        'projects' => 'Projects', 'time' => 'Time tracked', 'signals' => 'Business signals',
    ],

    // Default tenant settings; a row in the settings table overrides each key
    'defaults' => [
        'invoice_prefix' => 'INV', 'invoice_next_number' => 1, 'invoice_reset' => 'yearly', 'invoice_padding' => 5,
        'invoice_branch_sequence' => false, 'quote_prefix' => 'QUO', 'payment_prefix' => 'PAY',
        'invoice_due_days' => 14, 'invoice_terms' => '', 'invoice_notes' => '', 'invoice_footer' => 'Thank you for your business.',
        'invoice_template' => 'classic', 'invoice_font' => 'DejaVu Sans', 'invoice_color' => '#0F6B4F',
        'invoice_columns' => ['description', 'quantity', 'unit', 'price', 'discount', 'tax'],
        'invoice_show_tax' => true, 'invoice_show_qr' => true, 'invoice_show_payment_link' => true,
        'invoice_payment_instructions' => '', 'invoice_bank_details' => '',
        'tax_system' => 'exclusive', 'tax_label' => 'Tax', 'tax_exempt' => false,
        'currency_display' => 'symbol',
        'reminders_enabled' => true, 'reminder_before_days' => 3, 'reminder_on_due' => true, 'reminder_after_days' => [3, 7, 15],
        'digest_daily' => false, 'digest_weekly' => true, 'digest_monthly' => true,
        'two_factor_required' => false, 'track_invoice_views' => true,
        'portal_enabled' => true, 'white_label' => false, 'email_from_name' => '', 'email_footer' => '',
    ],

    'expense_categories' => ['Software', 'Salary', 'Rent', 'Marketing', 'Transportation', 'Utilities', 'Equipment', 'Miscellaneous'],

    // Limit keys every plan can define (plan_features table)
    'limits' => ['max_users', 'max_customers', 'max_invoices', 'max_products', 'max_storage', 'max_locations', 'max_projects'],

    'features' => ['advanced_reports', 'inventory', 'projects', 'time_tracking', 'business_health', 'custom_branding',
        'api_access', 'automation', 'customer_portal', 'webhooks', 'white_label', 'custom_domain', 'recurring_invoices'],

    /*
     | Testimonials on the home page. Leave empty until you have real quotes from real customers,
     | with their permission. The section is hidden while this list is empty.
     | Example: ['quote' => '...', 'name' => 'Jane Doe', 'role' => 'Owner, Example Studio']
     */
    'testimonials' => [],

    'rtl_locales' => ['ar', 'ur', 'he', 'fa'],
    'locales' => ['en' => 'English', 'ur' => 'اردو', 'ar' => 'العربية', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch', 'pt' => 'Português', 'tr' => 'Türkçe'],

    'upload' => [
        'max_kb' => 10240,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'csv', 'txt', 'doc', 'docx', 'xls', 'xlsx'],
    ],

    'rate_limits' => ['api' => 60, 'login' => 5, 'public' => 30, 'sensitive' => 10],

    'backup' => ['enabled' => (bool) env('BACKUP_ENABLED', false), 'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30)],
];
