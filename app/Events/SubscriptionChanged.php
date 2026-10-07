<?php

namespace App\Events;

use App\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;

class SubscriptionChanged
{
    use Dispatchable;

    public function __construct(public Subscription $subscription) {}
}
