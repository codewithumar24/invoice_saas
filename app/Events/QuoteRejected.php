<?php

namespace App\Events;

use App\Models\Quote;
use Illuminate\Foundation\Events\Dispatchable;

class QuoteRejected
{
    use Dispatchable;

    public function __construct(public Quote $quote) {}
}
