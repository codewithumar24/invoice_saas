<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\RegisterController;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** The signed-in user's own profile, password, two-factor authentication, sessions and notification preferences. */
class AccountController extends Controller
{
    public const NOTIFICATION_CATEGORIES = [
        'invoices' => 'Invoice notifications', 'payments' => 'Payment notifications', 'expenses' => 'Expense notifications',
        'inventory' => 'Inventory notifications', 'system' => 'System notifications', 'digest' => 'Business briefs',
        'security' => 'Security notifications', 'marketing' => 'Product news',
    ];

    public function profile(Request $request)
    {
        return view('account.profile', ['user' => $request->user(), 'timezones' => timezone_identifiers_list()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'timezone' => ['nullable', 'timezone'],
            'locale' => ['nullable', Rule::in(array_keys(config('saas.locales')))],
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
        ]);

        $emailChanged = $data['email'] !== $user->email;
        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            RegisterController::sendVerification($user);
        }

        return back()->with('success', __('Profile saved.'));
    }

    public function security(Request $request)
    {
        $user = $request->user();
        $sessions = config('session.driver') === 'database'
            ? DB::table('sessions')->where('user_id', $user->id)->orderByDesc('last_activity')->get()->map(fn ($session) => (object) [
                'id' => $session->id, 'ip' => $session->ip_address, 'agent' => $session->user_agent,
                'last_active' => \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity),
                'current' => $session->id === $request->session()->getId(),
            ])
            : null;

        return view('account.security', [
            'user' => $user,
            'sessions' => $sessions,
            'pendingSecret' => $request->session()->get('two_factor_setup'),
            'otpauth' => $request->session()->has('two_factor_setup')
                ? app(TwoFactorService::class)->otpauthUrl($request->session()->get('two_factor_setup'), $user->email, config('app.name')) : null,
            'recoveryCodes' => $request->session()->get('recovery_codes'),
        ]);
    }

    public function updatePassword(Request $request, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // logoutOtherDevices re-hashes the password, which signs out every other session
        Auth::logoutOtherDevices($data['current_password']);
        $request->user()->forceFill(['password' => $data['password']])->save();
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();

        $notifications->email($request->user()->email, 'security_alert', [
            'user_name' => $request->user()->name, 'alert_message' => __('The password of your account was changed.'),
            'time' => now()->toDayDateTimeString().' UTC', 'ip' => $request->ip(),
        ]);
        app(AuditService::class)->log('auth.password_changed', $request->user()->email);

        return back()->with('success', __('Password changed. Other sessions were signed out.'));
    }

    /* ------------------------------ Two-factor authentication ------------------------------ */

    public function enableTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        // The secret is only saved to the account after the first code is confirmed
        $request->session()->put('two_factor_setup', $twoFactor->generateSecret());

        return redirect()->route('account.security')->with('success', __('Scan the QR code, then enter a code to confirm.'));
    }

    public function confirmTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $secret = $request->session()->get('two_factor_setup');

        if (! $secret || ! $twoFactor->verify($secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => __('The code is not valid. Check the time on your phone and try again.')]);
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $request->user()->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $codes, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('two_factor_setup');
        app(AuditService::class)->log('auth.2fa_enabled', $request->user()->email);

        return redirect()->route('account.security')->with('recovery_codes', $codes)->with('success', __('Two-factor authentication is on. Save your backup codes now.'));
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        abort_unless($request->user()->hasTwoFactor(), 404);
        $codes = $twoFactor->generateRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return redirect()->route('account.security')->with('recovery_codes', $codes)->with('success', __('New backup codes generated. The old ones no longer work.'));
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $request->session()->forget('two_factor_setup');
        app(AuditService::class)->log('auth.2fa_disabled', $request->user()->email);

        return back()->with('success', __('Two-factor authentication is off.'));
    }

    /* ------------------------------ Sessions ------------------------------ */

    public function destroySession(Request $request, string $id): RedirectResponse
    {
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', $id)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('success', __('Session signed out.'));
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password']]);
        Auth::logoutOtherDevices($data['current_password']);
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('success', __('All other sessions were signed out.'));
    }

    /* ------------------------------ Notification preferences ------------------------------ */

    public function notifications(Request $request)
    {
        return view('account.notifications', ['user' => $request->user(), 'categories' => self::NOTIFICATION_CATEGORIES]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $preferences = [];

        foreach (array_keys(self::NOTIFICATION_CATEGORIES) as $category) {
            $preferences[$category] = [
                'app' => $request->boolean("preferences.{$category}.app"),
                'email' => $request->boolean("preferences.{$category}.email"),
            ];
        }

        $request->user()->forceFill(['notification_preferences' => $preferences])->save();

        return back()->with('success', __('Notification preferences saved.'));
    }
}
