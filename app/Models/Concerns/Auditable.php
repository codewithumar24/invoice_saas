<?php

namespace App\Models\Concerns;

use App\Services\AuditService;

/** Writes an audit log entry whenever a sensitive record is created, changed or deleted. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => app(AuditService::class)->record('created', $model, [], $model->auditValues($model->getAttributes())));

        static::updated(function ($model) {
            $changes = $model->auditValues($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);
            app(AuditService::class)->record('updated', $model, $model->auditValues($old), $changes);
        });

        static::deleted(fn ($model) => app(AuditService::class)->record('deleted', $model, $model->auditValues($model->getOriginal()), []));
    }

    protected function auditValues(array $values): array
    {
        $hidden = array_merge(['updated_at', 'created_at', 'portal_password', 'public_token', 'config', 'secret'], $this->getHidden());

        return array_diff_key($values, array_flip($hidden));
    }

    public function auditLabel(): string
    {
        return (string) ($this->number ?? $this->name ?? $this->getKey());
    }
}
