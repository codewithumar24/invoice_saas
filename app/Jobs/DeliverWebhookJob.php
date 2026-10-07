<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use RuntimeException;

/** Delivers one outgoing webhook; failed deliveries are retried with a growing delay. */
class DeliverWebhookJob extends TenantJob
{
    public int $tries = 5;

    public function __construct(int $tenantId, public int $deliveryId)
    {
        parent::__construct($tenantId);
        $this->onQueue('notifications');
    }

    /** Seconds to wait before retry 2, 3, 4 and 5. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function process(WebhookService $webhooks): void
    {
        $delivery = WebhookDelivery::with('webhook')->find($this->deliveryId);

        if (! $delivery || ! $delivery->webhook || $delivery->status === 'success') {
            return;
        }

        if (! $webhooks->deliver($delivery)) {
            throw new RuntimeException("Webhook delivery {$delivery->id} failed (attempt {$delivery->attempts}).");
        }
    }
}
