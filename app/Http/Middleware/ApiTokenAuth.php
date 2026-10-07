<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Tenant;
use App\Services\AuditService;
use App\Services\UsageService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** API authentication with scoped bearer tokens. Only the SHA-256 hash of a token is stored. */
class ApiTokenAuth
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next, ?string $ability = null)
    {
        $plain = (string) $request->bearerToken();
        $token = $plain !== '' ? ApiToken::withoutGlobalScope('tenant')->with('user')->where('token_hash', hash('sha256', $plain))->first() : null;

        if (! $token || ($token->expires_at && $token->expires_at->isPast()) || $token->user?->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'errors' => (object) []], 401);
        }

        $tenant = Tenant::find($token->tenant_id);

        if (! $tenant || ! $tenant->isActive()) {
            return response()->json(['success' => false, 'message' => 'Workspace is not active.', 'errors' => (object) []], 403);
        }

        $this->context->set($tenant);

        if (! feature('api_access')) {
            return response()->json(['success' => false, 'message' => 'API access is not available on this plan.', 'errors' => (object) []], 403);
        }

        Auth::setUser($token->user);
        $request->attributes->set('api_token', $token);

        // The token must carry the scope AND its user must still hold the matching permission
        if ($ability) {
            [$resource, $action] = explode(':', $ability);
            $permission = $resource.'.'.($action === 'read' ? 'view' : 'create');

            if (! $token->can($ability) || ! $token->user->hasPermission($permission)) {
                return response()->json(['success' => false, 'message' => "This token lacks the [{$ability}] scope.", 'errors' => (object) []], 403);
            }
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();
        app(UsageService::class)->increment('api_calls');

        $response = $next($request);

        if (! $request->isMethodSafe()) {
            app(AuditService::class)->log('api.'.strtolower($request->method()), $request->path(), ['token' => $token->name, 'status' => $response->getStatusCode()]);
        }

        return $response;
    }
}
