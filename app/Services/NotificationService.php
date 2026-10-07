<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/** Sends in-app and email notifications, respecting each user's per-category channel preferences. */
class NotificationService
{
    public function __construct(private TenantContext $context, private EmailTemplateService $templates, private UsageService $usage) {}

    /** Active members of the current workspace, optionally only those holding a permission. */
    public function recipients(?string $permission = null): Collection
    {
        $tenant = $this->context->get();

        if (! $tenant) {
            return collect();
        }

        return $tenant->users()->wherePivot('status', 'active')->get()
            ->filter(fn (User $user) => $permission === null || $user->hasPermission($permission))->values();
    }

    /**
     * Notify team members.
     *
     * @param  string  $category  invoices|payments|expenses|inventory|security|marketing|system
     */
    public function team(string $category, string $title, ?string $message = null, ?string $url = null, ?string $permission = null, bool $email = false): void
    {
        foreach ($this->recipients($permission) as $user) {
            $this->user($user, $category, $title, $message, $url, $email);
        }
    }

    public function user(User $user, string $category, string $title, ?string $message = null, ?string $url = null, bool $email = false): void
    {
        if ($user->wantsNotification($category, 'app')) {
            $user->notify(new GenericNotification($category, $title, $message, $url, $this->context->id()));
        }

        if ($email && $user->wantsNotification($category, 'email')) {
            $html = '<p>'.e($message ?? $title).'</p>';
            Mail::to($user->email)->queue(new TemplateMail($title, $html, $this->branding(), [], $url, __('Open')));
            $this->usage->increment('emails_sent');
        }
    }

    /** Send a templated email (database-driven template) to any address. */
    public function email(string $to, string $templateKey, array $variables, array $files = [], ?string $actionUrl = null, ?string $actionLabel = null): void
    {
        $rendered = $this->templates->render($templateKey, $variables);
        Mail::to($to)->queue(new TemplateMail($rendered['subject'], $rendered['html'], $this->branding(), $files, $actionUrl, $actionLabel));
        $this->usage->increment('emails_sent');
    }

    /** Email branding: the workspace's own name/colour/logo, or the platform's. */
    public function branding(?Tenant $tenant = null): array
    {
        $tenant ??= $this->context->get();

        if (! $tenant) {
            return ['name' => config('app.name'), 'color' => '#0F6B4F', 'logo_url' => null, 'footer' => null];
        }

        return [
            'name' => setting('email_from_name') ?: $tenant->name,
            'color' => $tenant->primary_color ?: '#0F6B4F',
            'logo_url' => $tenant->logo_path ? app(FileStorageService::class)->publicUrl($tenant->logo_path) : null,
            'footer' => setting('email_footer') ?: null,
            'white_label' => (bool) setting('white_label') && feature('white_label'),
        ];
    }
}
