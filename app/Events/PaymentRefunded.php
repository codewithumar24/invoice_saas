<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

class PaymentRefunded
{
    use Dispatchable;

    public function __construct(public Payment $payment) {}
}
