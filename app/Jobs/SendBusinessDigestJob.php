<?php

namespace App\Jobs;

use App\Services\BusinessHealthService;
use App\Services\NotificationService;
use App\Services\SettingsService;

/** Daily / weekly / monthly business brief by email. Each workspace can switch each one off. */
class SendBusinessDigestJob extends TenantJob
{
    public function __construct(int $tenantId, public string $period)
    {
        parent::__construct($tenantId);
        $this->onQueue('reports');
    }

    public function process(BusinessHealthService $health, NotificationService $notifications, SettingsService $settings): void
    {
        if (! $settings->get("digest_{$this->period}") || ! feature('business_health')) {
            return;
        }

        $text = $health->briefText($this->period);

        foreach ($notifications->recipients('health.view') as $user) {
            if (! $user->wantsNotification('digest', 'email')) {
                continue;
            }

            $notifications->email($user->email, 'business_digest', [
                'user_name' => $user->name, 'period' => __($this->period), 'digest' => $text, 'action_url' => route('dashboard'),
            ], [], route('dashboard'), __('Open dashboard'));
        }
    }
}
