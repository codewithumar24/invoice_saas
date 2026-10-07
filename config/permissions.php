<?php

// Permission catalogue. Rows are synced to the "permissions" table; roles and their permissions live in the database.
return [
    'groups' => [
        'dashboard' => ['view'],
        'health' => ['view'],
        'customers' => ['view', 'create', 'update', 'delete'],
        'invoices' => ['view', 'create', 'update', 'delete', 'send', 'download', 'duplicate'],
        'quotes' => ['view', 'create', 'update', 'delete', 'send'],
        'payments' => ['view', 'create', 'refund'],
        'expenses' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete'],
        'inventory' => ['view', 'manage'],
        'projects' => ['view', 'manage'],
        'time' => ['track'],
        'reports' => ['view', 'export'],
        'automation' => ['manage'],
        'documents' => ['view', 'manage'],
        'team' => ['view', 'manage'],
        'roles' => ['manage'],
        'settings' => ['view', 'update'],
        'billing' => ['manage'],
        'audit' => ['view'],
        'api' => ['manage'],
    ],

    // Default roles created for every new workspace. "*" = every permission, "x.*" = a whole group.
    'roles' => [
        'owner' => ['name' => 'Owner', 'description' => 'Complete access', 'permissions' => ['*']],
        'administrator' => ['name' => 'Administrator', 'description' => 'Business management access', 'permissions' => ['*'], 'except' => ['billing.manage']],
        'accountant' => ['name' => 'Accountant', 'description' => 'Financial modules', 'permissions' => ['dashboard.view', 'health.view', 'customers.view', 'invoices.*', 'payments.*', 'expenses.*', 'reports.*', 'quotes.view', 'documents.view']],
        'sales_manager' => ['name' => 'Sales Manager', 'description' => 'Customers, quotes and sales', 'permissions' => ['dashboard.view', 'customers.*', 'quotes.*', 'invoices.view', 'invoices.create', 'invoices.update', 'invoices.send', 'invoices.download', 'invoices.duplicate', 'products.view', 'reports.view', 'documents.view']],
        'sales_staff' => ['name' => 'Sales Staff', 'description' => 'Customers, quotes and invoices', 'permissions' => ['dashboard.view', 'customers.view', 'customers.create', 'customers.update', 'quotes.view', 'quotes.create', 'quotes.update', 'quotes.send', 'invoices.view', 'invoices.create', 'invoices.send', 'invoices.download', 'products.view']],
        'inventory_manager' => ['name' => 'Inventory Manager', 'description' => 'Products, stock and inventory', 'permissions' => ['dashboard.view', 'products.*', 'inventory.*']],
        'employee' => ['name' => 'Employee', 'description' => 'Assigned operational access', 'permissions' => ['dashboard.view', 'customers.view', 'projects.view', 'time.track']],
        'viewer' => ['name' => 'Viewer', 'description' => 'Read-only access', 'permissions' => ['dashboard.view', 'health.view', 'customers.view', 'invoices.view', 'quotes.view', 'payments.view', 'expenses.view', 'products.view', 'inventory.view', 'projects.view', 'reports.view', 'documents.view', 'team.view']],
    ],
];
