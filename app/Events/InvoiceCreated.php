<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

class InvoiceCreated
{
    use Dispatchable;

    public function __construct(public Invoice $invoice) {}
}
