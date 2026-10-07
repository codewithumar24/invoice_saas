<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

/*
| Public API, versioned from the first release: /api/v1/...
| Auth: "Authorization: Bearer <token>" (tokens are created in Settings -> API tokens).
| Each route names the token scope it needs. Requests are rate limited and write calls are audited.
*/

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::middleware('api.token:customers:read')->group(function () {
        Route::get('customers', [ApiController::class, 'customers']);
        Route::get('customers/{customer}', [ApiController::class, 'showCustomer']);
    });
    Route::middleware('api.token:customers:write')->group(function () {
        Route::post('customers', [ApiController::class, 'storeCustomer']);
        Route::put('customers/{customer}', [ApiController::class, 'updateCustomer']);
    });

    Route::middleware('api.token:invoices:read')->group(function () {
        Route::get('invoices', [ApiController::class, 'invoices']);
        Route::get('invoices/{invoice}', [ApiController::class, 'showInvoice']);
    });
    Route::post('invoices', [ApiController::class, 'storeInvoice'])->middleware('api.token:invoices:write');

    Route::get('payments', [ApiController::class, 'payments'])->middleware('api.token:payments:read');
    Route::post('invoices/{invoice}/payments', [ApiController::class, 'storePayment'])->middleware('api.token:payments:write');

    Route::middleware('api.token:products:read')->group(function () {
        Route::get('products', [ApiController::class, 'products']);
        Route::get('products/{product}', [ApiController::class, 'showProduct']);
    });
    Route::post('products', [ApiController::class, 'storeProduct'])->middleware('api.token:products:write');

    Route::get('expenses', [ApiController::class, 'expenses'])->middleware('api.token:expenses:read');
    Route::post('expenses', [ApiController::class, 'storeExpense'])->middleware('api.token:expenses:write');
});
