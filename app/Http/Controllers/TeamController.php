<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FeatureService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        $tenant = tenant();
        $members = $tenant->users()->orderBy('name')->get();
        $roles = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.tenant_id', $tenant->id)->get(['user_roles.user_id', 'roles.id', 'roles.name'])->keyBy('user_id');

        return view('team.index', [
            'members' => $members, 'memberRoles' => $roles,
            'roles' => Role::where('slug', '!=', 'owner')->orderBy('name')->get(),
            'invitations' => Invitation::with(['role', 'inviter'])->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(),
        ]);
    }

    public function invite(Request $request, NotificationService $notifications, FeatureService $features): RedirectResponse
    {
        $tenant = tenant();
        $features->enforceLimit('max_users', $tenant->users()->count() + Invitation::whereNull('accepted_at')->where('expires_at', '>', now())->count(), __('team members'));

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)->whereNot('slug', 'owner')],
        ]);

        if ($tenant->users()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => __('This person is already a member of the workspace.')]);
        }

        Invitation::where('email', $data['email'])->whereNull('accepted_at')->delete();
        $invitation = Invitation::create($data + ['token' => Str::random(64), 'invited_by' => $request->user()->id, 'expires_at' => now()->addDays(7)]);

        $url = route('invitations.show', $invitation->token);
        $notifications->email($data['email'], 'team_invitation', [
            'inviter_name' => $request->user()->name, 'role_name' => $invitation->role->name, 'action_url' => $url,
        ], [], $url, __('Accept invitation'));

        return back()->with('success', __('Invitation sent to :email.', ['email' => $data['email']]));
    }

    public function cancelInvitation(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        return back()->with('success', __('Invitation cancelled.'));
    }

    public function updateMember(Request $request, User $user): RedirectResponse
    {
        $tenant = tenant();
        $this->guardMember($request, $user);

        $data = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('tenant_id', $tenant->id)->whereNot('slug', 'owner')],
        ]);

        DB::transaction(function () use ($user, $tenant, $data) {
            DB::table('user_roles')->where('user_id', $user->id)->where('tenant_id', $tenant->id)->delete();
            DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $data['role_id'], 'tenant_id' => $tenant->id]);
        });

        $user->flushPermissionCache($tenant->id);
        app(AuditService::class)->log('team.role_changed', $user->email, ['role_id' => $data['role_id']]);

        return back()->with('success', __('Role updated.'));
    }

    public function toggleMember(Request $request, User $user): RedirectResponse
    {
        $this->guardMember($request, $user);
        $status = tenant()->users()->where('users.id', $user->id)->first()->pivot->status === 'active' ? 'suspended' : 'active';
        tenant()->users()->updateExistingPivot($user->id, ['status' => $status]);
        app(AuditService::class)->log('team.member_'.$status, $user->email);

        return back()->with('success', $status === 'suspended' ? __('Member suspended.') : __('Member reactivated.'));
    }

    /** "Reset access": sign the member out everywhere and email them a password reset link. */
    public function resetAccess(Request $request, User $user, NotificationService $notifications): RedirectResponse
    {
        $this->guardMember($request, $user);
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], ['token' => bcrypt($token), 'created_at' => now()]);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);
        $notifications->email($user->email, 'password_reset', ['user_name' => $user->name, 'action_url' => $url], [], $url, __('Reset password'));
        app(AuditService::class)->log('team.access_reset', $user->email);

        return back()->with('success', __(':name was signed out and sent a password reset link.', ['name' => $user->name]));
    }

    public function removeMember(Request $request, User $user): RedirectResponse
    {
        $tenant = tenant();
        $this->guardMember($request, $user);

        DB::transaction(function () use ($user, $tenant) {
            DB::table('user_roles')->where('user_id', $user->id)->where('tenant_id', $tenant->id)->delete();
            $tenant->users()->detach($user->id);

            if ($user->current_tenant_id === $tenant->id) {
                $user->forceFill(['current_tenant_id' => null])->save();
            }
        });

        $user->flushPermissionCache($tenant->id);
        app(AuditService::class)->log('team.member_removed', $user->email);

        return back()->with('success', __('Member removed from the workspace.'));
    }

    public function activity(User $user)
    {
        abort_unless(tenant()->users()->where('users.id', $user->id)->exists(), 404);

        return view('team.activity', ['member' => $user, 'activities' => ActivityLog::where('user_id', $user->id)->latest()->paginate(30)]);
    }

    /** The target must be a member of this workspace, must not be its owner, and must not be yourself. */
    private function guardMember(Request $request, User $user): void
    {
        $member = tenant()->users()->where('users.id', $user->id)->first();
        abort_unless($member, 404);
        abort_if($member->pivot->is_owner, 403, __('The workspace owner cannot be changed here.'));
        abort_if($user->id === $request->user()->id, 403, __('You cannot change your own membership.'));
    }

    /* ------------------------------ Roles & permissions ------------------------------ */

    public function roles()
    {
        return view('team.roles', ['roles' => Role::withCount('permissions')->orderBy('is_system', 'desc')->orderBy('name')->get()]);
    }

    public function createRole()
    {
        return view('team.role-form', ['role' => new Role(), 'selected' => [], 'groups' => Permission::orderBy('id')->get()->groupBy('group')]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $data = $this->validatedRole($request);
        $slug = Str::slug($data['name'], '_');

        if (Role::where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => __('A role with this name already exists.')])->withInput();
        }

        $role = Role::create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'slug' => $slug]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('team.roles')->with('success', __('Role created.'));
    }

    public function editRole(Role $role)
    {
        abort_if($role->slug === 'owner', 403, __('The owner role always has complete access.'));

        return view('team.role-form', ['role' => $role, 'selected' => $role->permissions()->pluck('permissions.id')->all(), 'groups' => Permission::orderBy('id')->get()->groupBy('group')]);
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->slug === 'owner', 403);
        $data = $this->validatedRole($request);
        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
        $role->permissions()->sync($data['permissions'] ?? []);

        // Members holding this role get the new permissions on their next request
        $role->users()->get()->each->flushPermissionCache(tenant()->id);
        app(AuditService::class)->log('role.permissions_changed', $role->name, ['permissions' => count($data['permissions'] ?? [])]);

        return redirect()->route('team.roles')->with('success', __('Role saved.'));
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', __('Default roles cannot be deleted. You can change their permissions instead.'));
        }

        if ($role->users()->exists()) {
            return back()->with('error', __('Move the members of this role to another role first.'));
        }

        $role->delete();

        return back()->with('success', __('Role deleted.'));
    }

    private function validatedRole(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);
    }
}
