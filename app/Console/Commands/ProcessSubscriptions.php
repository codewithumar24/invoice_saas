<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptions extends Command
{
    protected $signature = 'saas:subscriptions';

    protected $description = 'End trials, renew manually billed subscriptions and expire cancelled ones';

    public function handle(SubscriptionService $subscriptions): int
    {
        $result = $subscriptions->processRenewals();
        $this->info("Renewed {$result['renewed']}, expired {$result['expired']}.");

        return self::SUCCESS;
    }
}
