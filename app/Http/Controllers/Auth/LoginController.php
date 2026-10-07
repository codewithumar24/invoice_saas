<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::where('email', $credentials['email'])->first();

        // Same error for "unknown email" and "wrong password", so accounts cannot be discovered
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->track($request, false);
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        if ($user->status !== 'active') {
            $this->track($request, false);
            throw ValidationException::withMessages(['email' => __('This account is suspended. Please contact support.')]);
        }

        // Password is correct. With 2FA switched on, the user is not signed in until the code is verified.
        if ($user->hasTwoFactor()) {
            $request->session()->put('two_factor', ['user_id' => $user->id, 'remember' => $request->boolean('remember'), 'expires' => now()->addMinutes(10)->timestamp]);

            return redirect()->route('two-factor.challenge');
        }

        return $this->complete($request, $user, $request->boolean('remember'));
    }

    public function showChallenge(Request $request)
    {
        return $this->pendingUser($request) ? view('auth.two-factor-challenge') : redirect()->route('login');
    }

    public function challenge(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['nullable', 'string', 'max:20'], 'recovery_code' => ['nullable', 'string', 'max:40']]);
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $key = 'two-factor:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $valid = false;

        if ($request->filled('recovery_code')) {
            $codes = $user->two_factor_recovery_codes ?? [];
            $given = strtolower(trim($request->input('recovery_code')));

            if (in_array($given, $codes, true)) {
                // A backup code works once
                $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$given]))])->save();
                $valid = true;
            }
        } elseif ($request->filled('code')) {
            $valid = $twoFactor->verify($user->two_factor_secret, $request->input('code'));
        }

        if (! $valid) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['code' => __('The code is not valid.')]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('two_factor.remember');
        $request->session()->forget('two_factor');

        return $this->complete($request, $user, $remember);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('two_factor');

        if (! $pending || $pending['expires'] < now()->timestamp) {
            return null;
        }

        return User::find($pending['user_id']);
    }

    private function complete(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate(); // prevents session fixation
        $this->track($request, true);

        // Tell the user about a sign-in from an address we have not seen before
        if ($user->last_login_ip && $user->last_login_ip !== $request->ip()) {
            app(NotificationService::class)->email($user->email, 'security_alert', [
                'user_name' => $user->name, 'alert_message' => __('Your account was signed in to from a new IP address.'),
                'time' => now()->toDayDateTimeString().' UTC', 'ip' => $request->ip(),
            ]);
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        app(AuditService::class)->log('auth.login', $user->email, [], $user->current_tenant_id);

        $default = $user->is_super_admin && ! $user->tenants()->exists() ? route('admin.dashboard') : route('dashboard');

        return redirect()->intended($default);
    }

    private function track(Request $request, bool $successful): void
    {
        LoginAttempt::create([
            'email' => mb_substr((string) $request->input('email', Auth::user()?->email), 0, 255),
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'successful' => $successful,
            'created_at' => now(),
        ]);
    }
}
