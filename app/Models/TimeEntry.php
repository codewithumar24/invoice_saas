<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends TenantModel
{
    protected function casts(): array
    {
        return ['entry_date' => \App\Casts\DateOnly::class, 'started_at' => 'datetime', 'is_billable' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Seconds tracked so far, including the segment that is still running. */
    public function elapsedSeconds(): int
    {
        $running = $this->status === 'running' && $this->started_at ? (int) $this->started_at->diffInSeconds(now()) : 0;

        return $this->duration_seconds + $running;
    }

    public function hours(): float
    {
        return round($this->elapsedSeconds() / 3600, 2);
    }

    public static function formatDuration(int $seconds): string
    {
        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
