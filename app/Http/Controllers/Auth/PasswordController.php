<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Forgot password: email -> secure one-time token (stored hashed, valid 60 minutes) -> new password. */
class PasswordController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request, NotificationService $notifications): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $request->input('email'))->first();

        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], ['token' => Hash::make($token), 'created_at' => now()]);
            $url = route('password.reset', ['token' => $token, 'email' => $user->email]);
            $notifications->email($user->email, 'password_reset', ['user_name' => $user->name, 'action_url' => $url], [], $url, __('Reset password'));
        }

        // Same answer whether or not the address exists
        return back()->with('success', __('If that email address has an account, a reset link is on its way.'));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request, NotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        $user = User::where('email', $data['email'])->first();

        if (! $record || ! $user || ! Hash::check($data['token'], $record->token) || now()->subMinutes(60)->gt($record->created_at)) {
            throw ValidationException::withMessages(['email' => __('This password reset link is invalid or has expired.')]);
        }

        $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete(); // sign out everywhere

        $notifications->email($user->email, 'security_alert', [
            'user_name' => $user->name, 'alert_message' => __('The password of your account was changed.'),
            'time' => now()->toDayDateTimeString().' UTC', 'ip' => $request->ip(),
        ]);

        return redirect()->route('login')->with('success', __('Your password has been reset. You can sign in now.'));
    }
}
