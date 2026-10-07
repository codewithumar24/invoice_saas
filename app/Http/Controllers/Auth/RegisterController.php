<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TenantService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function show(Request $request)
    {
        abort_unless(platform_setting('registration_enabled', true), 403, __('Registration is currently closed.'));

        return view('auth.register', ['timezones' => timezone_identifiers_list(), 'countries' => config('countries')]);
    }

    public function register(Request $request, TenantService $tenants): RedirectResponse
    {
        abort_unless(platform_setting('registration_enabled', true), 403, __('Registration is currently closed.'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'country' => ['required', 'string', 'size:2', Rule::in(array_keys(config('countries')))],
            'timezone' => ['required', 'timezone'],
            'business_name' => ['required', 'string', 'max:255'],
            'terms' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($data, $tenants, $request) {
            $user = User::create(collect($data)->only(['name', 'email', 'password', 'country', 'timezone'])->all());
            $tenants->create($user, ['name' => $data['business_name'], 'country' => $data['country'], 'timezone' => $data['timezone']], $request->session()->get('referral_code'));

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        self::sendVerification($user);

        return redirect()->route('verification.notice');
    }

    public static function sendVerification(User $user): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        app(NotificationService::class)->email($user->email, 'verification', ['user_name' => $user->name, 'action_url' => $url], [], $url, __('Verify email address'));
    }

    public function verificationNotice(Request $request)
    {
        return $request->user()->hasVerifiedEmail() ? redirect()->route('dashboard') : view('auth.verify-email');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            self::sendVerification($request->user());
        }

        return back()->with('success', __('A new verification link has been sent to your email address.'));
    }

    /** The link is signed, so it cannot be forged or altered. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->email), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            app(NotificationService::class)->email($user->email, 'welcome', [
                'user_name' => $user->name, 'business_name' => $user->tenants()->first()?->name, 'action_url' => route('dashboard'),
            ], [], route('dashboard'), __('Open your workspace'));
        }

        return redirect()->route(Auth::check() ? 'dashboard' : 'login')->with('success', __('Your email address is verified.'));
    }

    /* ------------------------------ Team invitations ------------------------------ */

    public function showInvitation(string $token)
    {
        $invitation = $this->invitation($token);

        return view('auth.invitation', [
            'invitation' => $invitation,
            'tenant' => Tenant::find($invitation->tenant_id),
            'existingUser' => User::where('email', $invitation->email)->exists(),
        ]);
    }

    public function acceptInvitation(Request $request, string $token, TenantContext $context): RedirectResponse
    {
        $invitation = $this->invitation($token);
        $tenant = Tenant::findOrFail($invitation->tenant_id);
        $user = User::where('email', $invitation->email)->first();

        if ($user) {
            // Existing account: the invited person must prove it is theirs
            $request->validate(['password' => ['required', 'string']]);
            if (! Auth::validate(['email' => $user->email, 'password' => $request->input('password')])) {
                return back()->withErrors(['password' => __('The password is incorrect.')]);
            }
        } else {
            $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::defaults()]]);
            $user = new User(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password'], 'timezone' => $tenant->timezone]);
            $user->email_verified_at = now(); // the invitation link proves ownership of the address
            $user->save();
        }

        DB::transaction(function () use ($invitation, $tenant, $user) {
            $tenant->users()->syncWithoutDetaching([$user->id => ['is_owner' => false, 'status' => 'active']]);
            DB::table('user_roles')->where('user_id', $user->id)->where('tenant_id', $tenant->id)->delete();
            DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $invitation->role_id, 'tenant_id' => $tenant->id]);
            $invitation->forceFill(['accepted_at' => now()])->save();
            $user->forceFill(['current_tenant_id' => $tenant->id])->save();
            $user->flushPermissionCache($tenant->id);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', __('Welcome to :name.', ['name' => $tenant->name]));
    }

    private function invitation(string $token): Invitation
    {
        $invitation = Invitation::withoutGlobalScope('tenant')->where('token', $token)->first();
        abort_unless($invitation && $invitation->isPending(), 404, __('This invitation is no longer valid.'));

        return $invitation;
    }
}
