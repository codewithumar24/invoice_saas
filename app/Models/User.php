<?php

namespace App\Models;

use App\Support\TenantContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'country', 'timezone', 'locale', 'theme'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    private array $ownerCache = [];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'dashboard_config' => 'array',
            'notification_preferences' => 'array',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('is_owner', 'status', 'branch_id')->withTimestamps();
    }

    /** Roles in the current workspace (Role is tenant scoped). */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withPivot('tenant_id');
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function isOwnerOf(?int $tenantId): bool
    {
        if (! $tenantId) {
            return false;
        }

        return $this->ownerCache[$tenantId] ??= DB::table('tenant_user')
            ->where('tenant_id', $tenantId)->where('user_id', $this->id)->where('is_owner', true)->exists();
    }

    /** All permission names of this user inside one workspace (cached, flushed when roles change). */
    public function permissionsFor(int $tenantId): array
    {
        return Cache::remember("perms:{$tenantId}:{$this->id}", 600, function () use ($tenantId) {
            return DB::table('permissions')
                ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
                ->where('user_roles.user_id', $this->id)
                ->where('user_roles.tenant_id', $tenantId)
                ->distinct()
                ->pluck('permissions.name')
                ->all();
        });
    }

    public function hasPermission(string $permission): bool
    {
        $tenantId = app(TenantContext::class)->id();

        if (! $tenantId) {
            return false;
        }

        // The workspace owner always has complete access
        if ($this->isOwnerOf($tenantId)) {
            return true;
        }

        return in_array($permission, $this->permissionsFor($tenantId), true);
    }

    public function flushPermissionCache(int $tenantId): void
    {
        Cache::forget("perms:{$tenantId}:{$this->id}");
    }

    /** Does the user want this notification category on this channel? Defaults to yes. */
    public function wantsNotification(string $category, string $channel): bool
    {
        // Security notices cannot be switched off
        if ($category === 'security') {
            return true;
        }

        return (bool) data_get($this->notification_preferences, "{$category}.{$channel}", $category !== 'marketing');
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
