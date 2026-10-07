<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** In-app notification shown in the notification centre. */
class GenericNotification extends Notification
{
    public function __construct(
        public string $category,
        public string $title,
        public ?string $message = null,
        public ?string $url = null,
        public ?int $tenantId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'tenant_id' => $this->tenantId,
        ];
    }
}
