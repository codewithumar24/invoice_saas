<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/** Business timeline shared by every entity (customer timeline, invoice history, dashboard feed). */
class ActivityService
{
    public function log(string $event, string $description, ?Model $subject = null, ?int $customerId = null, array $properties = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => auth()->id(),
            'customer_id' => $customerId,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties ?: null,
        ]);
    }
}
