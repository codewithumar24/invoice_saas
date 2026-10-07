<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

/** Append-only audit trail. Nothing in the application updates or deletes audit rows. */
class AuditService
{
    public function __construct(private TenantContext $context) {}

    public function record(string $action, Model $model, array $old = [], array $new = []): void
    {
        $this->write($action, class_basename($model), $model->getKey(),
            method_exists($model, 'auditLabel') ? $model->auditLabel() : null, $old, $new,
            $model->tenant_id ?? $this->context->id());
    }

    /** Audit an action that is not a simple model change (login, impersonation, export...). */
    public function log(string $action, ?string $label = null, array $details = [], ?int $tenantId = null): void
    {
        $this->write($action, null, null, $label, [], $details, $tenantId ?? $this->context->id());
    }

    private function write(string $action, ?string $type, mixed $id, ?string $label, array $old, array $new, ?int $tenantId): void
    {
        $request = app()->runningInConsole() ? null : request();

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'impersonator_id' => $request?->hasSession() ? $request->session()->get('impersonator_id') : null,
            'action' => $action,
            'entity_type' => $type,
            'entity_id' => $id,
            'entity_label' => $label ? mb_substr($label, 0, 250) : null,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }
}
