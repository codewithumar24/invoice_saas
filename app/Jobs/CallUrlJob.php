<?php

namespace App\Jobs;

use App\Support\SafeUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/** Automation action "call a webhook URL". */
class CallUrlJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $url, public array $payload)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        if (SafeUrl::isPublic($this->url)) {
            Http::timeout(10)->post($this->url, $this->payload)->throw();
        }
    }
}
