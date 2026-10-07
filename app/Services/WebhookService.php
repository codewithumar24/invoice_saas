<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Outgoing webhooks: event -> queue -> signed HTTP request -> retry -> delivery log. */
class WebhookService
{
    public const EVENTS = ['invoice.created', 'invoice.sent', 'invoice.paid', 'invoice.overdue', 'payment.created',
        'customer.created', 'quote.accepted', 'subscription.updated'];

    public function __construct(private FeatureService $features) {}

    public function dispatch(string $event, array $data): void
    {
        if (! $this->features->enabled('webhooks')) {
            return;
        }

        $webhooks = Webhook::where('is_active', true)->get()
            ->filter(fn ($webhook) => in_array($event, $webhook->events ?? [], true) || in_array('*', $webhook->events ?? [], true));

        foreach ($webhooks as $webhook) {
            $delivery = $webhook->deliveries()->create([
                'event' => $event,
                'payload' => ['event' => $event, 'created_at' => now()->toIso8601String(), 'data' => $data],
                'status' => 'pending',
            ]);

            DeliverWebhookJob::dispatch($webhook->tenant_id, $delivery->id);
        }
    }

    /** Send one delivery. Returns true on a 2xx response. */
    public function deliver(WebhookDelivery $delivery): bool
    {
        $webhook = $delivery->webhook;
        $body = json_encode($delivery->payload);
        $timestamp = (string) time();

        $delivery->increment('attempts');

        try {
            $response = Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Event' => $delivery->event,
                'X-Webhook-Timestamp' => $timestamp,
                'X-Webhook-Signature' => self::sign($timestamp, $body, $webhook->secret),
            ])->withBody($body, 'application/json')->post($webhook->url);

            $delivery->update([
                'response_status' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'status' => $response->successful() ? 'success' : 'failed',
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            return $response->successful();
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'failed', 'response_body' => mb_substr($exception->getMessage(), 0, 2000)]);

            return false;
        }
    }

    /** Receivers recompute this value to verify the request: HMAC-SHA256 of "timestamp.body". */
    public static function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
