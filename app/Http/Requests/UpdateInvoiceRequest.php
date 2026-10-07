<?php

namespace App\Http\Requests;

class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    // Same rules as creating an invoice; kept separate so update-only rules have a home.
}
